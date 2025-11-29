<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Hold;

$productId = $argv[1] ?? null;
$qty = $argv[2] ?? 1;

if (! $productId) {
    echo "missing productId\n";
    exit(2);
}

// bootstrap the framework
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $hold = DB::transaction(function () use ($productId, $qty) {
        $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
        $active = $product->holds()->where('consumed', false)->where('expires_at', '>', now())->sum('qty');
        $available = max(0, $product->stock - $active);
        if ($available < $qty) {
            echo "FAILED\n";
            return null;
        }
        $hold = Hold::create(['product_id' => $productId, 'qty' => $qty, 'expires_at' => now()->addMinutes(2)]);
        echo "OK:{$hold->id}\n";
        return $hold;
    }, 5);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
