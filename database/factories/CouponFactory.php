<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('COUPON-####'),
            'type' => 'percentage',
            'value' => fake()->numberBetween(1, 30),
            'max_discount' => 50000,
            'minimum_purchase' => 100000,
            'usage_limit' => 100,
            'usage_per_customer' => 1,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }
}