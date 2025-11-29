<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;

class ProductTest extends TestCase
{
    public function test_product_show_returns_available()
    {
        $product = Product::create(['name' => 'P1', 'price_cents' => 500, 'stock' => 5]);

        $resp = $this->getJson("/api/products/{$product->id}");
        $resp->assertStatus(200)
            ->assertJsonStructure(['id','name','price_cents','stock','available'])
            ->assertJson(['available' => $product->available()]);
    }
}
