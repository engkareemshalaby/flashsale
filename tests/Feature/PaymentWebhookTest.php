<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Models\Order;

class PaymentWebhookTest extends TestCase
{
    public function test_webhook_idempotency()
    {
        $product = Product::create(['name' => 'W1', 'price_cents' => 100, 'stock' => 1]);
        $hold = Hold::create(['product_id' => $product->id, 'qty' => 1, 'expires_at' => now()->addMinutes(5)]);

        $orderResp = $this->postJson('/api/orders', ['hold_id' => $hold->id]);
        $orderResp->assertStatus(200);
        $orderId = $orderResp->json('order_id');

        $payload = ['idempotency_key' => 'idemp-1', 'hold_id' => $hold->id, 'status' => 'paid'];
        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);
        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);

        $order = Order::find($orderId);
        $this->assertEquals('paid', $order->status);
    }

    public function test_webhook_arrives_before_order_creation_is_applied()
    {
        $product = Product::create(['name' => 'W2', 'price_cents' => 100, 'stock' => 1]);
        $hold = Hold::create(['product_id' => $product->id, 'qty' => 1, 'expires_at' => now()->addMinutes(5)]);

        $payload = ['idempotency_key' => 'before-1', 'hold_id' => $hold->id, 'status' => 'paid'];
        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);

        $orderResp = $this->postJson('/api/orders', ['hold_id' => $hold->id]);
        $orderResp->assertStatus(200);

        $order = Order::find($orderResp->json('order_id'));
        $this->assertEquals('paid', $order->status);
    }
}
