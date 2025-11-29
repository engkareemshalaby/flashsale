<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hold extends Model
{
    protected $fillable = ['product_id','qty','expires_at','consumed'];
    protected $casts = [
        'expires_at' => 'datetime',
        'consumed' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
