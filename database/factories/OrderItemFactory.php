<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = 100000;
        $quantity = 1;

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),

            'product_name' => fake()->words(3, true),
            'product_sku' => fake()->unique()->bothify('SKU-####'),

            'price' => $price,
            'quantity' => $quantity,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'line_total' => $price * $quantity,
        ];
    }
}