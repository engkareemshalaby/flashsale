<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'idempotency_key' => 'required|string',
            'hold_id' => 'required|integer',
            'status' => 'required|in:paid,failed',
        ];
    }
}
