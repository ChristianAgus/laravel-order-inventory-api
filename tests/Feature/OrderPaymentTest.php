<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class OrderPaymentTest extends TestCase
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

    private function createOrder(
        User $user,
        Product $product,
        string $idempotencyKey
    ): Order {
        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'idempotency_key' => $idempotencyKey,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertCreated();

        return Order::findOrFail(
            $response->json('data.id')
        );
    }

    public function test_admin_dapat_membayar_pending_order(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $product = $this->product();

        $order = $this->createOrder(
            $customer,
            $product,
            'payment-001'
        );

        $response = $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_customer_tidak_dapat_memanggil_endpoint_pay(): void
    {
        $customer = $this->customer();
        $product = $this->product();

        $order = $this->createOrder(
            $customer,
            $product,
            'payment-002'
        );

        $response = $this->actingAs($customer)
            ->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(403);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_admin_tidak_dapat_membayar_cancelled_order(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $product = $this->product();

        $order = $this->createOrder(
            $customer,
            $product,
            'payment-003'
        );

        $this->actingAs($customer)
            ->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $response = $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_admin_tidak_dapat_membayar_expired_order(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $product = $this->product();

        $order = $this->createOrder(
            $customer,
            $product,
            'payment-004'
        );

        $expiredAt = Carbon::now()->subMinutes(31);

        $order->created_at = $expiredAt;
        $order->save();

        $order->refresh();

        $this->assertTrue(
            $order->created_at->lt(now()->subMinutes(30))
        );

        $response = $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'expired',
        ]);
    }

    public function test_payment_dipanggil_dua_kali_tidak_menimbulkan_efek_ganda(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $product = $this->product();

        $order = $this->createOrder(
            $customer,
            $product,
            'payment-005'
        );

        $firstResponse = $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/pay");

        $firstResponse->assertOk();

        $firstPaidAt = Order::findOrFail($order->id)->paid_at;

        $secondResponse = $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/pay");

        $secondResponse->assertOk();

        $secondPaidAt = Order::findOrFail($order->id)->paid_at;

        $this->assertNotNull($firstPaidAt);
        $this->assertNotNull($secondPaidAt);

        $this->assertEquals(
            $firstPaidAt->toDateTimeString(),
            $secondPaidAt->toDateTimeString()
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_pembayaran_berhasil_mengisi_paid_at(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $product = $this->product();

        $order = $this->createOrder(
            $customer,
            $product,
            'payment-006'
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'paid_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertOk();

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
            'paid_at' => null,
        ]);
    }
}