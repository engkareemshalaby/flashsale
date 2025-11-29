<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function show($id)
    {
        $product = Product::findOrFail($id);
        $cacheKey = "product:{$id}:available";

        $available = Cache::remember($cacheKey, 30, function () use ($product) {
            return $product->available();
        });

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'price_cents' => $product->price_cents,
            'stock' => $product->stock,
            'available' => $available,
        ]);
    }
}
