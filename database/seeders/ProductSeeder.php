<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // Create 10 products with varying price and stock for easier testing
        for ($i = 1; $i <= 10; $i++) {
            Product::updateOrCreate(
                ['name' => "Product {$i}"],
                ['price_cents' => 500 * $i, 'stock' => max(1, 6 - ($i % 6))]
            );
        }
    }
}
