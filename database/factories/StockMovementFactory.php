<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'order_id' => null,

            'type' => 'checkout',
            'quantity' => 1,
            'stock_before' => 10,
            'stock_after' => 9,

            'description' => 'Test stock movement',
            'created_at' => now(),
        ];
    }
}