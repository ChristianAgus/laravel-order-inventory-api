<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create([
            'role' => 'customer',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
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
        string $idempotencyKey = 'cancel-test-key'
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

    private function createOrder(
        User $user,
        Product $product,
        int $quantity = 1,
        string $idempotencyKey = 'cancel-test-key'
    ): Order {
        $response = $this->actingAs($user)
            ->postJson('/api/orders', $this->orderPayload(
                $product,
                $quantity,
                $idempotencyKey
            ));

        $response->assertCreated();

        return Order::findOrFail(
            $response->json('data.id')
        );
    }

    public function test_customer_dapat_membatalkan_pending_order_dalam_30_menit(): void
    {
        $user = $this->customer();

        $product = $this->product([
            'stock' => 10,
        ]);

        $order = $this->createOrder(
            $user,
            $product,
            2,
            'cancel-001'
        );

        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_pembatalan_order_mengembalikan_stock(): void
    {
        $user = $this->customer();

        $product = $this->product([
            'stock' => 10,
        ]);

        $order = $this->createOrder(
            $user,
            $product,
            3,
            'cancel-002'
        );

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 7,
        ]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);
    }

    public function test_pembatalan_membuat_stock_movement_cancellation(): void
    {
        $user = $this->customer();

        $product = $this->product([
            'stock' => 10,
        ]);

        $order = $this->createOrder(
            $user,
            $product,
            2,
            'cancel-003'
        );

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'cancellation',
            'quantity' => 2,
            'stock_before' => 8,
            'stock_after' => 10,
        ]);
    }

    public function test_order_paid_tidak_dapat_dibatalkan(): void
    {
        $admin = $this->admin();
        $user = $this->customer();

        $product = $this->product();

        $order = $this->createOrder(
            $user,
            $product,
            1,
            'cancel-004'
        );

        $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertOk();

        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_order_setelah_30_menit_tidak_dapat_dibatalkan(): void
    {
        $user = $this->customer();

        $product = $this->product([
            'stock' => 10,
        ]);

        $order = $this->createOrder(
            $user,
            $product,
            2,
            'cancel-005'
        );

        $expiredAt = Carbon::now()->subMinutes(31);

        $order->created_at = $expiredAt;
        $order->save();

        $order->refresh();

        $this->assertTrue(
            $order->created_at->lt(now()->subMinutes(30))
        );

        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);
    }
    
    public function test_customer_tidak_dapat_membatalkan_order_customer_lain(): void
    {
        $owner = $this->customer();
        $otherUser = $this->customer();

        $product = $this->product();

        $order = $this->createOrder(
            $owner,
            $product,
            1,
            'cancel-006'
        );

        $response = $this->actingAs($otherUser)
            ->postJson("/api/orders/{$order->id}/cancel");

        $this->assertContains(
            $response->status(),
            [403, 404, 422]
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_order_tidak_dapat_dibatalkan_dua_kali(): void
    {
        $user = $this->customer();

        $product = $this->product([
            'stock' => 10,
        ]);

        $order = $this->createOrder(
            $user,
            $product,
            2,
            'cancel-007'
        );

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);

        $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 10,
        ]);

        $this->assertDatabaseCount('stock_movements', 2);
    }
}