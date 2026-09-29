<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-' . fake()->unique()->numerify('##########'),
            'user_id' => User::factory(),
            'idempotency_key' => fake()->unique()->bothify('test-key-####'),
            'status' => 'pending_payment',

            'subtotal' => 100000,
            'product_discount' => 0,
            'coupon_discount' => 0,
            'taxable_amount' => 100000,
            'tax_amount' => 11000,
            'shipping_cost' => 25000,
            'grand_total' => 136000,

            'coupon_id' => null,
            'paid_at' => null,
            'cancelled_at' => null,
        ];
    }
}