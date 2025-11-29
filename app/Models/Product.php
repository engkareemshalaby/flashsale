<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'price_cents', 'stock'];

    public function holds()
    {
        return $this->hasMany(Hold::class);
    }

    public function available(): int
    {
        $active = $this->holds()->where('consumed', false)->where('expires_at', '>', now())->sum('qty');
        return max(0, $this->stock - $active);
    }
}
