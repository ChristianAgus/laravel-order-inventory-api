<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(
                trim($this->input('code', ''))
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:coupons,code',
            ],

            'type' => [
                'required',
                Rule::in([
                    'percentage',
                    'fixed',
                ]),
            ],

            'value' => [
                'required',
                'integer',
                'min:1',
            ],

            'max_discount' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'minimum_purchase' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'usage_limit' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'usage_per_customer' => [
                'required',
                'integer',
                'min:1',
            ],

            'starts_at' => [
                'required',
                'date',
            ],

            'expires_at' => [
                'required',
                'date',
                'after:starts_at',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');
            $value = $this->input('value');
            $maxDiscount = $this->input('max_discount');

            if (
                $type === 'percentage'
                && (
                    ! is_numeric($value)
                    || $value < 1
                    || $value > 100
                )
            ) {
                $validator->errors()->add(
                    'value',
                    'Percentage coupon value must be between 1 and 100.'
                );
            }

            if (
                $type === 'fixed'
                && $maxDiscount !== null
            ) {
                $validator->errors()->add(
                    'max_discount',
                    'max_discount can only be used for percentage coupons.'
                );
            }
        });
    }
}