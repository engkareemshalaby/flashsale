<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookRequest;
use App\Models\WebhookIdempotency;
use App\Models\Order;
use App\Models\Hold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PaymentWebhookController extends Controller
{
    public function handle(WebhookRequest $request)
    {
        $data = $request->validated();
        $key = $data['idempotency_key'];
        $holdId = $data['hold_id'];
        $status = $data['status'];

        try {
            return DB::transaction(function () use ($key, $holdId, $status, $data) {
                $existing = WebhookIdempotency::where('key', $key)->first();
                if ($existing) {
                    Log::info('Duplicate webhook received', ['key' => $key]);
                    return response()->json(['ok' => true]);
                }

                $web = WebhookIdempotency::create([
                    'key' => $key,
                    'entity_type' => 'hold',
                    'entity_id' => $holdId,
                    'payload' => $data,
                ]);

                $order = Order::whereHas('hold', function ($q) use ($holdId) {
                    $q->where('id', $holdId);
                })->lockForUpdate()->first();

                if ($order) {
                    if ($status === 'paid') {
                        $order->status = 'paid';
                        $order->save();
                    } else {
                        $order->status = 'cancelled';
                        $order->save();

                        $hold = $order->hold;
                        $hold->expires_at = now()->subSeconds(1);
                        $hold->save();
                        Cache::forget("product:{$hold->product_id}:available");
                    }
                }

                return response()->json(['ok' => true]);
            });
        } catch (\Exception $e) {
            Log::error('Webhook handling error', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false], 500);
        }
    }
}
