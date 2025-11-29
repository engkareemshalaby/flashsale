<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Hold;
use App\Models\Order;
use App\Models\WebhookIdempotency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function store(OrderRequest $request)
    {
        $data = $request->validated();
        $holdId = $data['hold_id'];

        $order = DB::transaction(function () use ($holdId) {
            $hold = Hold::where('id', $holdId)->lockForUpdate()->firstOrFail();

            if ($hold->consumed || $hold->expires_at->isPast()) {
                return null;
            }

            $order = Order::create(['hold_id' => $hold->id, 'status' => 'pre_payment']);
            $hold->consumed = true;
            $hold->save();

            Cache::forget("product:{$hold->product_id}:available");

            return $order;
        });

        if (! $order) {
            return response()->json(['message' => 'Invalid or expired hold'], 409);
        }

        $web = WebhookIdempotency::where('entity_type', 'hold')->where('entity_id', $holdId)->first();
        if ($web) {
            $payload = $web->payload ?? [];
            if (! empty($payload['status']) && $payload['status'] === 'paid') {
                $order->status = 'paid';
                $order->save();
            } elseif (! empty($payload['status']) && $payload['status'] === 'failed') {
                $order->status = 'cancelled';
                $order->save();
            }
        }

        return response()->json(['order_id' => $order->id, 'status' => $order->status]);
    }
}
