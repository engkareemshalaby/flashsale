<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;

class OrderTest extends TestCase
{
    public function test_create_order_consumes_hold()
    {
        $product = Product::create(['name' => 'O1', 'price_cents' => 300, 'stock' => 2]);
        $hold = Hold::create(['product_id' => $product->id, 'qty' => 1, 'expires_at' => now()->addMinutes(5)]);

        $resp = $this->postJson('/api/orders', ['hold_id' => $hold->id]);
        $resp->assertStatus(200);

        $orderId = $resp->json('order_id');
        $this->assertNotNull(Order::find($orderId));

        $this->assertTrue(Hold::find($hold->id)->consumed);
    }
}
