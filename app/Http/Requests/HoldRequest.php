<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'required|integer|min:1',
        ];
    }
}
