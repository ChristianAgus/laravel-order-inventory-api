<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create([
            'role' => 'customer',
        ]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 100000,
            'stock' => 10,
            'discount_percent' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function orderPayload(
        Product $product,
        string $idempotencyKey
    ): array {
        return [
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];
    }

    public function test_stock_one_hanya_dapat_dibeli_satu_order(): void
    {
        $user1 = $this->customer();
        $user2 = $this->customer();

        $product = $this->product([
            'stock' => 1,
        ]);

        $firstResponse = $this->actingAs($user1)
            ->postJson('/api/orders', $this->orderPayload(
                $product,
                'concurrency-001'
            ));

        $firstResponse->assertCreated();

        $secondResponse = $this->actingAs($user2)
            ->postJson('/api/orders', $this->orderPayload(
                $product,
                'concurrency-002'
            ));

        $secondResponse->assertStatus(422);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 0,
        ]);

        $this->assertDatabaseCount('orders', 1);

        $this->assertDatabaseCount('stock_movements', 1);
    }
}