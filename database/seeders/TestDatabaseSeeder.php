<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Customer Test',
            'email' => 'customer@test.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        User::create([
            'name' => 'Customer Two Test',
            'email' => 'customer2@test.com',
            'password' => Hash::make('password123'),
            'role' => 'customer',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */

        Product::create([
            'sku' => 'TEST-LAPTOP-001',
            'name' => 'Laptop Lenovo Test',
            'price' => 5000000,
            'stock' => 10,
            'discount_percent' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'sku' => 'TEST-LAPTOP-002',
            'name' => 'Laptop ASUS Test',
            'price' => 7000000,
            'stock' => 20,
            'discount_percent' => 0,
            'is_active' => true,
        ]);

        Product::create([
            'sku' => 'TEST-MOUSE-001',
            'name' => 'Wireless Mouse Test',
            'price' => 150000,
            'stock' => 50,
            'discount_percent' => 5,
            'is_active' => true,
        ]);

        Product::create([
            'sku' => 'TEST-KEYBOARD-001',
            'name' => 'Mechanical Keyboard Test',
            'price' => 500000,
            'stock' => 30,
            'discount_percent' => 0,
            'is_active' => true,
        ]);

        Product::create([
            'sku' => 'TEST-INACTIVE-001',
            'name' => 'Inactive Product Test',
            'price' => 300000,
            'stock' => 10,
            'discount_percent' => 0,
            'is_active' => false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Coupons
        |--------------------------------------------------------------------------
        */

        Coupon::create([
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 50000,
            'minimum_purchase' => 100000,
            'usage_limit' => 100,
            'usage_per_customer' => 1,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'TESTFIXED',
            'type' => 'fixed',
            'value' => 25000,
            'max_discount' => null,
            'minimum_purchase' => 100000,
            'usage_limit' => 100,
            'usage_per_customer' => 1,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'TESTEXPIRED',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 50000,
            'minimum_purchase' => 0,
            'usage_limit' => 100,
            'usage_per_customer' => 1,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'TESTINACTIVE',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 50000,
            'minimum_purchase' => 0,
            'usage_limit' => 100,
            'usage_per_customer' => 1,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => false,
        ]);
    }
}