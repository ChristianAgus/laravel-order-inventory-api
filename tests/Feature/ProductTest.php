<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class ProductTest extends TestCase
{
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

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes);
    }

    public function test_admin_dapat_membuat_produk(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'sku' => 'TEST-001',
                'name' => 'Product Test',
                'price' => 100000,
                'stock' => 50,
                'discount_percent' => 10,
                'is_active' => true,
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.sku', 'TEST-001')
            ->assertJsonPath('data.name', 'Product Test');

        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001',
            'name' => 'Product Test',
        ]);
    }

    public function test_customer_tidak_dapat_membuat_produk(): void
    {
        $customer = $this->customer();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->postJson('/api/products', [
                'sku' => 'TEST-002',
                'name' => 'Product Test',
                'price' => 100000,
                'stock' => 50,
                'discount_percent' => 0,
                'is_active' => true,
            ]);

        $response->assertStatus(403);
    }

    public function test_sku_produk_harus_unique(): void
    {
        $admin = $this->admin();

        $this->product([
            'sku' => 'DUPLICATE-SKU',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'sku' => 'DUPLICATE-SKU',
                'name' => 'Another Product',
                'price' => 100000,
                'stock' => 50,
                'discount_percent' => 0,
                'is_active' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_harga_tidak_boleh_negatif(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'sku' => 'PRICE-001',
                'name' => 'Invalid Price',
                'price' => -1,
                'stock' => 10,
                'discount_percent' => 0,
                'is_active' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_stok_tidak_boleh_negatif(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'sku' => 'STOCK-001',
                'name' => 'Invalid Stock',
                'price' => 100000,
                'stock' => -1,
                'discount_percent' => 0,
                'is_active' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_dapat_melihat_detail_produk(): void
    {
        $admin = $this->admin();

        $product = $this->product([
            'sku' => 'DETAIL-001',
            'name' => 'Detail Product',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson("/api/products/{$product->id}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', 'DETAIL-001');
    }

    public function test_produk_tidak_ditemukan(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/products/999999999');

        $response->assertStatus(404);
    }

    public function test_admin_dapat_memperbarui_produk(): void
    {
        $admin = $this->admin();

        $product = $this->product([
            'sku' => 'UPDATE-001',
            'name' => 'Old Name',
            'price' => 100000,
            'stock' => 10,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson("/api/products/{$product->id}", [
                'sku' => 'UPDATE-001',
                'name' => 'New Name',
                'price' => 200000,
                'stock' => 20,
                'discount_percent' => 15,
                'is_active' => true,
            ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'New Name',
            'price' => 200000,
            'stock' => 20,
        ]);
    }

    public function test_update_tidak_boleh_menggunakan_sku_produk_lain(): void
    {
        $admin = $this->admin();

        $productA = $this->product([
            'sku' => 'SKU-A',
        ]);

        $this->product([
            'sku' => 'SKU-B',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->putJson("/api/products/{$productA->id}", [
                'sku' => 'SKU-B',
                'name' => 'Updated',
                'price' => 100000,
                'stock' => 10,
                'discount_percent' => 0,
                'is_active' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_dapat_soft_delete_produk(): void
    {
        $admin = $this->admin();

        $product = $this->product([
            'sku' => 'DELETE-001',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    public function test_produk_yang_dihapus_tidak_muncul_untuk_customer(): void
    {
        $customer = $this->customer();

        $product = $this->product([
            'sku' => 'DELETED-001',
            'is_active' => true,
        ]);

        $product->delete();

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson("/api/products/{$product->id}");

        $response->assertStatus(404);
    }

    public function test_filter_produk_berdasarkan_status_aktif(): void
    {
        $admin = $this->admin();

        $activeProduct = $this->product([
            'sku' => 'ACTIVE-001',
            'is_active' => true,
        ]);

        $inactiveProduct = $this->product([
            'sku' => 'INACTIVE-001',
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/products?is_active=1');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($activeProduct->id));
        $this->assertFalse($ids->contains($inactiveProduct->id));
    }

    public function test_customer_hanya_melihat_produk_aktif(): void
    {
        $customer = $this->customer();

        $activeProduct = $this->product([
            'is_active' => true,
        ]);

        $inactiveProduct = $this->product([
            'is_active' => false,
        ]);

        $response = $this
            ->actingAs($customer, 'sanctum')
            ->getJson('/api/products');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($activeProduct->id));
        $this->assertFalse($ids->contains($inactiveProduct->id));
    }

    public function test_search_produk_berdasarkan_nama(): void
    {
        $admin = $this->admin();

        $product = $this->product([
            'name' => 'Gaming Laptop ASUS',
            'sku' => 'LAPTOP-001',
        ]);

        $this->product([
            'name' => 'Office Chair',
            'sku' => 'CHAIR-001',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/products?search=Gaming');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($product->id));
    }

    public function test_search_produk_berdasarkan_sku(): void
    {
        $admin = $this->admin();

        $product = $this->product([
            'name' => 'Product A',
            'sku' => 'SPECIAL-SKU-999',
        ]);

        $this->product([
            'name' => 'Product B',
            'sku' => 'OTHER-001',
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/products?search=SPECIAL-SKU-999');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($product->id));
    }

    public function test_sort_produk_berdasarkan_harga(): void
    {
        $admin = $this->admin();

        $cheap = $this->product([
            'price' => 50000,
        ]);

        $expensive = $this->product([
            'price' => 200000,
        ]);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/products?sort=price&direction=asc');

        $response->assertStatus(200);

        $prices = collect($response->json('data'))
            ->pluck('price')
            ->map(fn ($price) => (int) $price)
            ->values();

        $this->assertTrue(
            $prices->search($cheap->price)
            < $prices->search($expensive->price)
        );
    }

    public function test_invalid_sort_column_menggunakan_default_aman(): void
    {
        $admin = $this->admin();

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->getJson('/api/products?sort=invalid_column');

        $response->assertStatus(200);
    }
}