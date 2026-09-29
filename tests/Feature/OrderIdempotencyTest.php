<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderIdempotencyTest extends TestCase
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
        int $quantity = 1,
        string $idempotencyKey = 'checkout-test-key'
    ): array {
        return [
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ],
            ],
        ];
    }

    public function test_idempotency_request_pertama_membuat_order(): void
    {
        $user = $this->customer();
        $product = $this->product();

        $response = $this->actingAs($user)
            ->postJson('/api/orders', $this->orderPayload(
                $product,
                2,
                'idem-key-001'
            ));

        $response->assertCreated();

        $this->assertDatabaseCount('orders', 1);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'idempotency_key' => 'idem-key-001',
        ]);
    }

    public function test_idempotency_request_kedua_dengan_key_sama_mengembalikan_order_pertama(): void
    {
        $user = $this->customer();
        $product = $this->product([
            'stock' => 10,
        ]);

        $payload = $this->orderPayload(
            $product,
            2,
            'idem-key-002'
        );

        $firstResponse = $this->actingAs($user)
            ->postJson('/api/orders', $payload);

        $firstResponse->assertCreated();

        $firstOrderId = $firstResponse->json('data.id');

        $secondResponse = $this->actingAs($user)
            ->postJson('/api/orders', $payload);

        $secondResponse->assertCreated();

        $secondOrderId = $secondResponse->json('data.id');

        $this->assertSame(
            $firstOrderId,
            $secondOrderId
        );

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_idempotency_request_kedua_tidak_mengurangi_stock_lagi(): void
    {
        $user = $this->customer();

        $product = $this->product([
            'stock' => 10,
        ]);

        $payload = $this->orderPayload(
            $product,
            3,
            'idem-key-003'
        );

        $this->actingAs($user)
            ->postJson('/api/orders', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 7,
        ]);

        $this->actingAs($user)
            ->postJson('/api/orders', $payload)
            ->assertCreated();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 7,
        ]);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_customer_lain_dengan_idempotency_key_sama_membuat_order_baru(): void
    {
        $user1 = $this->customer();
        $user2 = $this->customer();

        $product = $this->product([
            'stock' => 10,
        ]);

        $payload = $this->orderPayload(
            $product,
            1,
            'same-key'
        );

        $firstResponse = $this->actingAs($user1)
            ->postJson('/api/orders', $payload);

        $firstResponse->assertCreated();

        $secondResponse = $this->actingAs($user2)
            ->postJson('/api/orders', $payload);

        $secondResponse->assertCreated();

        $this->assertDatabaseCount('orders', 2);

        $this->assertDatabaseCount('stock_movements', 2);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user1->id,
            'idempotency_key' => 'same-key',
        ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user2->id,
            'idempotency_key' => 'same-key',
        ]);
    }
}