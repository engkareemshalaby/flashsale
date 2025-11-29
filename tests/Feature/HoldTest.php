<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Hold;
use App\Jobs\ExpireHold;

class HoldTest extends TestCase
{
    public function test_create_hold_reduces_availability()
    {
        $product = Product::create(['name' => 'H1', 'price_cents' => 200, 'stock' => 3]);

        $resp = $this->postJson('/api/holds', ['product_id' => $product->id, 'qty' => 2]);
        $resp->assertStatus(200);

        $body = $resp->json();
        $this->assertArrayHasKey('hold_id', $body);

        $product->refresh();
        $this->assertEquals(1, $product->available());
    }

    public function test_concurrent_attempts_do_not_oversell()
    {
        $product = Product::create(['name' => 'Concurrent', 'price_cents' => 100, 'stock' => 1]);

        $php = PHP_BINARY;
        $script = base_path('scripts/attempt_hold.php');

        $p1 = popen("$php $script {$product->id} 1", 'r');
        $p2 = popen("$php $script {$product->id} 1", 'r');

        $o1 = stream_get_contents($p1);
        $o2 = stream_get_contents($p2);

        pclose($p1);
        pclose($p2);

        $successes = 0;
        if (strpos($o1, 'OK:') !== false) $successes++;
        if (strpos($o2, 'OK:') !== false) $successes++;

        $this->assertLessThanOrEqual(1, $successes, 'At most one hold should succeed');
    }

    public function test_hold_expiry_releases_availability()
    {
        $product = Product::create(['name' => 'Expire', 'price_cents' => 150, 'stock' => 2]);

        $hold = Hold::create(['product_id' => $product->id, 'qty' => 2, 'expires_at' => now()->addSeconds(1)]);

        // Fast-forward: mark expired and run ExpireHold handler
        $hold->expires_at = now()->subSeconds(1);
        $hold->save();

        $job = new ExpireHold($hold->id);
        $job->handle();

        $this->assertEquals(0, $product->holds()->where('consumed', false)->where('expires_at', '>', now())->sum('qty'));
        $this->assertEquals(2, $product->available());
    }
}
