<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create([
            'role' => 'customer',
        ]);
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::factory()->create($attributes);
    }

    public function test_admin_dapat_membuat_coupon_percentage(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/coupons', [
                'code' => 'SAVE10',
                'type' => 'percentage',
                'value' => 10,
                'max_discount' => 50000,
                'minimum_purchase' => 100000,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'starts_at' => now()->subHour()->toDateTimeString(),
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'is_active' => true,
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonPath('data.value', 10);

        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
        ]);
    }

    public function test_admin_dapat_membuat_coupon_fixed(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/coupons', [
                'code' => 'FIXED50K',
                'type' => 'fixed',
                'value' => 50000,
                'max_discount' => null,
                'minimum_purchase' => 100000,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'starts_at' => now()->subHour()->toDateTimeString(),
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'is_active' => true,
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'FIXED50K')
            ->assertJsonPath('data.type', 'fixed')
            ->assertJsonPath('data.value', 50000);

        $this->assertDatabaseHas('coupons', [
            'code' => 'FIXED50K',
            'type' => 'fixed',
            'value' => 50000,
        ]);
    }

    public function test_customer_tidak_dapat_membuat_coupon(): void
    {
        $customer = $this->customer();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson('/api/coupons', [
                'code' => 'CUSTOMER10',
                'type' => 'percentage',
                'value' => 10,
                'max_discount' => 50000,
                'minimum_purchase' => 100000,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'starts_at' => now()->subHour()->toDateTimeString(),
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'is_active' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_percentage_tidak_boleh_lebih_dari_100(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/coupons', [
                'code' => 'INVALID100',
                'type' => 'percentage',
                'value' => 101,
                'max_discount' => 50000,
                'minimum_purchase' => 100000,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'starts_at' => now()->subHour()->toDateTimeString(),
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'is_active' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_expires_at_harus_setelah_starts_at(): void
    {
        $admin = $this->admin();

        $startsAt = now()->addDays(10);
        $expiresAt = now()->addDays(5);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/coupons', [
                'code' => 'INVALID-DATE',
                'type' => 'percentage',
                'value' => 10,
                'max_discount' => 50000,
                'minimum_purchase' => 100000,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'starts_at' => $startsAt->toDateTimeString(),
                'expires_at' => $expiresAt->toDateTimeString(),
                'is_active' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_kode_coupon_case_insensitive(): void
    {
        $admin = $this->admin();

        $this->coupon([
            'code' => 'SAVE10',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/coupons', [
                'code' => 'save10',
                'type' => 'percentage',
                'value' => 10,
                'max_discount' => 50000,
                'minimum_purchase' => 100000,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'starts_at' => now()->subHour()->toDateTimeString(),
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'is_active' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_fixed_coupon_tidak_boleh_memiliki_max_discount(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/coupons', [
                'code' => 'FIXED-MAX-TEST',
                'type' => 'fixed',
                'value' => 50000,
                'max_discount' => 10000,
                'minimum_purchase' => 100000,
                'usage_limit' => 100,
                'usage_per_customer' => 1,
                'starts_at' => now()->subHour()->toDateTimeString(),
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'is_active' => true,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'max_discount',
            ]);

        $this->assertDatabaseMissing('coupons', [
            'code' => 'FIXED-MAX-TEST',
        ]);
    }

    public function test_admin_dapat_menonaktifkan_coupon(): void
    {
        $admin = $this->admin();

        $coupon = $this->coupon([
            'code' => 'DISABLE-ME',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson("/api/coupons/{$coupon->id}", [
                'code' => 'DISABLE-ME',
                'type' => $coupon->type,
                'value' => $coupon->value,
                'max_discount' => $coupon->max_discount,
                'minimum_purchase' => $coupon->minimum_purchase,
                'usage_limit' => $coupon->usage_limit,
                'usage_per_customer' => $coupon->usage_per_customer,
                'starts_at' => $coupon->starts_at->toDateTimeString(),
                'expires_at' => $coupon->expires_at->toDateTimeString(),
                'is_active' => false,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'is_active' => false,
        ]);
    }
}
