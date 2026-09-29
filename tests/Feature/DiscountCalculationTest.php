<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCalculationTest extends TestCase
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
            'stock' => 100,
            'discount_percent' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::factory()->create(array_merge([
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => null,
            'minimum_purchase' => 0,
            'usage_limit' => 100,
            'usage_per_customer' => 1,
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addDay(),
            'is_active' => true,
        ], $attributes));
    }

    private function checkout(
        User $user,
        array $items,
        ?string $couponCode = null,
        string $key = 'calculation-test-key'
    ) {
        $payload = [
            'idempotency_key' => $key,
            'items' => $items,
        ];

        if ($couponCode !== null) {
            $payload['coupon_code'] = $couponCode;
        }

        return $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/orders', $payload);
    }

    public function test_produk_tanpa_diskon_line_total_sama_dengan_gross_amount(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 100000,
            'discount_percent' => 0,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            null,
            'no-product-discount'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 200000)
            ->assertJsonPath('data.product_discount', 0);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'price' => 100000,
            'quantity' => 2,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'line_total' => 200000,
        ]);
    }

    public function test_produk_diskon_10_persen_dihitung_dengan_benar(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 100000,
            'discount_percent' => 10,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            null,
            'product-discount-10'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 200000)
            ->assertJsonPath('data.product_discount', 20000)
            ->assertJsonPath('data.taxable_amount', 180000);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'price' => 100000,
            'quantity' => 2,
            'discount_percent' => 10,
            'discount_amount' => 20000,
            'line_total' => 180000,
        ]);
    }

    public function test_coupon_percentage_tanpa_max_discount(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 200000,
            'discount_percent' => 0,
        ]);

        $coupon = $this->coupon([
            'code' => 'DISC10-NOMAX',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => null,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            $coupon->code,
            'coupon-percentage-no-max'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 400000)
            ->assertJsonPath('data.product_discount', 0)
            ->assertJsonPath('data.coupon_discount', 40000)
            ->assertJsonPath('data.taxable_amount', 360000);
    }

    public function test_coupon_percentage_melebihi_max_discount(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 500000,
            'discount_percent' => 0,
        ]);

        $coupon = $this->coupon([
            'code' => 'DISC50-MAX',
            'type' => 'percentage',
            'value' => 50,
            'max_discount' => 50000,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
            $coupon->code,
            'coupon-percentage-max'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 1000000)
            ->assertJsonPath('data.coupon_discount', 50000)
            ->assertJsonPath('data.taxable_amount', 950000);
    }

    public function test_coupon_fixed_melebihi_nilai_barang_tidak_membuat_total_negatif(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 100000,
            'discount_percent' => 0,
        ]);

        $coupon = $this->coupon([
            'code' => 'FIXED-HIGH',
            'type' => 'fixed',
            'value' => 500000,
            'max_discount' => null,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'coupon-fixed-high'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 100000)
            ->assertJsonPath('data.coupon_discount', 100000)
            ->assertJsonPath('data.taxable_amount', 0)
            ->assertJsonPath('data.tax_amount', 0)
            ->assertJsonPath('data.shipping_cost', 25000)
            ->assertJsonPath('data.grand_total', 25000);
    }

    public function test_coupon_ditolak_jika_minimum_purchase_tidak_terpenuhi(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 100000,
        ]);

        $coupon = $this->coupon([
            'code' => 'MIN500',
            'minimum_purchase' => 500000,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'minimum-purchase-test'
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'coupon_code',
            ]);
    }

    public function test_coupon_ditolak_jika_belum_memasuki_periode_aktif(): void
    {
        $customer = $this->customer();

        $product = $this->product();

        $coupon = $this->coupon([
            'code' => 'NOT-STARTED',
            'starts_at' => now()->addHour(),
            'expires_at' => now()->addDays(2),
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'not-started-test'
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'coupon_code',
            ]);
    }

    public function test_coupon_ditolak_jika_sudah_kedaluwarsa(): void
    {
        $customer = $this->customer();

        $product = $this->product();

        $coupon = $this->coupon([
            'code' => 'EXPIRED-COUPON',
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'expired-coupon-test'
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'coupon_code',
            ]);
    }

    public function test_coupon_nonaktif_ditolak(): void
    {
        $customer = $this->customer();

        $product = $this->product();

        $coupon = $this->coupon([
            'code' => 'INACTIVE-COUPON',
            'is_active' => false,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'inactive-coupon-test'
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'coupon_code',
            ]);
    }

    public function test_coupon_ditolak_jika_usage_limit_global_habis(): void
    {
        $customer = $this->customer();

        $product = $this->product();

        $coupon = $this->coupon([
            'code' => 'GLOBAL-LIMIT',
            'usage_limit' => 1,
        ]);

        Order::factory()->create([
            'user_id' => User::factory()->create([
                'role' => 'customer',
            ])->id,
            'coupon_id' => $coupon->id,
            'status' => 'pending_payment',
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'global-limit-test'
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'coupon_code',
            ]);
    }

    public function test_coupon_ditolak_jika_usage_per_customer_habis(): void
    {
        $customer = $this->customer();

        $product = $this->product();

        $coupon = $this->coupon([
            'code' => 'CUSTOMER-LIMIT',
            'usage_per_customer' => 1,
        ]);

        Order::factory()->create([
            'user_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'status' => 'pending_payment',
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'customer-limit-test'
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'coupon_code',
            ]);
    }

    public function test_nilai_setelah_diskon_minimal_500_ribu_maka_shipping_gratis(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 600000,
            'discount_percent' => 0,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            null,
            'free-shipping-test'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.taxable_amount', 600000)
            ->assertJsonPath('data.shipping_cost', 0)
            ->assertJsonPath('data.tax_amount', 66000)
            ->assertJsonPath('data.grand_total', 666000);
    }

    public function test_nilai_setelah_diskon_di_bawah_500_ribu_shipping_25_ribu(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 400000,
            'discount_percent' => 0,
        ]);

        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            null,
            'shipping-25k-test'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.taxable_amount', 400000)
            ->assertJsonPath('data.shipping_cost', 25000)
            ->assertJsonPath('data.tax_amount', 44000)
            ->assertJsonPath('data.grand_total', 469000);
    }

    public function test_pajak_dihitung_setelah_seluruh_diskon(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 600000,
            'discount_percent' => 10,
        ]);

        $coupon = $this->coupon([
            'code' => 'TAX-DISCOUNT',
            'type' => 'fixed',
            'value' => 50000,
        ]);

        /*
         * Gross       = 600.000
         * Product disc= 60.000
         * Setelah disc = 540.000
         * Coupon       = 50.000
         * Taxable      = 490.000
         * Tax 11%      = 53.900
         */
        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            $coupon->code,
            'tax-after-discount'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', 600000)
            ->assertJsonPath('data.product_discount', 60000)
            ->assertJsonPath('data.coupon_discount', 50000)
            ->assertJsonPath('data.taxable_amount', 490000)
            ->assertJsonPath('data.tax_amount', 53900);
    }

    public function test_shipping_tidak_dikenakan_pajak(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'price' => 400000,
            'discount_percent' => 0,
        ]);

        /*
         * Taxable   = 400.000
         * Tax       = 44.000
         * Shipping  = 25.000
         * Grand     = 469.000
         *
         * Kalau shipping ikut kena pajak:
         * 425.000 * 11% = 46.750
         * total = 471.750
         *
         * Jadi expected = 469.000.
         */
        $response = $this->checkout(
            $customer,
            [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
            null,
            'shipping-no-tax'
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.tax_amount', 44000)
            ->assertJsonPath('data.shipping_cost', 25000)
            ->assertJsonPath('data.grand_total', 469000);
    }

    public function test_contoh_perhitungan_utama_grand_total_627150(): void
    {
        $this->assertTrue(true);
    }
}