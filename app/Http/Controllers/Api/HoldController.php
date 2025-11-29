<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HoldRequest;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Jobs\ExpireHold;
use Illuminate\Support\Facades\Log;

class HoldController extends Controller
{
    public function store(HoldRequest $request)
    {
        $data = $request->validated();
        $productId = $data['product_id'];
        $qty = $data['qty'];

        $hold = DB::transaction(function () use ($productId, $qty) {
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $active = $product->holds()->where('consumed', false)->where('expires_at', '>', now())->sum('qty');
            $available = max(0, $product->stock - $active);

            if ($available < $qty) {
                Log::warning('Hold failed: insufficient stock', ['product_id' => $productId, 'requested' => $qty, 'available' => $available]);
                return null;
            }

            $hold = Hold::create([
                'product_id' => $productId,
                'qty' => $qty,
                'expires_at' => now()->addMinutes(2),
            ]);

            Cache::forget("product:{$productId}:available");

            return $hold;
        }, 5);

        if (! $hold) {
            return response()->json(['message' => 'Insufficient stock'], 409);
        }

        ExpireHold::dispatch($hold->id)->delay(now()->addMinutes(2));

        return response()->json(['hold_id' => $hold->id, 'expires_at' => $hold->expires_at]);
    }
}
