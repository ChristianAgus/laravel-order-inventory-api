<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:255',
                'unique:products,sku',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'price' => [
                'required',
                'integer',
                'min:1',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'discount_percent' => [
                'required',
                'integer',
                'between:0,100',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }
}