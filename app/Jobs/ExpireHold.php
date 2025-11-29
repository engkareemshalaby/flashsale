<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Models\Hold;
use Illuminate\Support\Facades\Cache;

class ExpireHold implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $holdId;

    public function __construct($holdId)
    {
        $this->holdId = $holdId;
    }

    public function handle()
    {
        $hold = Hold::find($this->holdId);
        if (! $hold) return;

        if ($hold->consumed) {
            return;
        }

        if ($hold->expires_at->isPast()) {
            Cache::forget("product:{$hold->product_id}:available");
            Log::info('Hold expired and cache invalidated', ['hold_id' => $hold->id]);
        }
    }
}
