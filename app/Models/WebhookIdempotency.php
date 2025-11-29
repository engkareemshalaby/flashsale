<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookIdempotency extends Model
{
    protected $fillable = ['key','entity_type','entity_id','payload'];
    protected $casts = [
        'payload' => 'array',
    ];
}
