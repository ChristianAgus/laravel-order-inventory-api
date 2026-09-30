# Laravel Order Inventory API

REST API untuk mengelola produk, inventory, checkout, order, coupon, cancellation, dan payment menggunakan Laravel.

Project ini dibuat sebagai technical test dengan fokus pada business logic checkout, data consistency, idempotency, stock management, dan automated testing.

## Tech Stack

* PHP 8.3+
* Laravel 13
* Laravel Sanctum
* MySQL
* PHPUnit
* REST API
* Git

---

## Features

* User registration and authentication
* Token-based authentication menggunakan Laravel Sanctum
* Customer dan admin role
* Product listing dengan search, filter, sorting, dan pagination
* Product management untuk admin
* Checkout/order creation
* Product-level discount
* Percentage dan fixed coupon
* Coupon usage limit
* Idempotent checkout
* Stock deduction
* Stock movement tracking
* Database transaction
* Row-level locking untuk mencegah overselling
* Order cancellation
* Automatic stock restoration ketika order dibatalkan
* Order expiration setelah 30 menit
* Admin payment processing
* Order ownership protection
* Automated feature tests

---

# Requirements

Pastikan environment sudah memiliki:

* PHP 8.3 atau lebih baru
* Composer
* MySQL
* Laravel 13 compatible environment

Cek versi:

```bash
php -v
composer --version
```

---

# Installation

Clone repository:

```bash
git clone https://github.com/ChristianAgus/laravel-order-inventory-api.git
```

Masuk ke directory project:

```bash
cd laravel-order-inventory-api
```

Install PHP dependencies:

```bash
composer install
```

Copy environment file:

```bash
cp .env.example .env
```

Untuk Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

---

# Database Configuration

Buat database MySQL terlebih dahulu.

Contoh:

```sql
CREATE DATABASE order_inventory;
```

Kemudian sesuaikan konfigurasi `.env`:

```env
APP_NAME="Order Inventory API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=order_inventory
DB_USERNAME=root
DB_PASSWORD=
```

Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` dengan konfigurasi MySQL lokal.

---

# Migration

Jalankan migration:

```bash
php artisan migrate
```

Untuk membuat database sekaligus menjalankan seluruh migration dan seeder:

```bash
php artisan migrate --seed
```

Seeder menyediakan akun dan data untuk kebutuhan pengujian API.

> **Warning:** `php artisan migrate:fresh --seed` akan menghapus seluruh tabel dan data pada database yang digunakan. Gunakan hanya pada environment development/testing.

---

# Seeder

Seeder menyediakan data berikut.

## Admin

```text
Email    : admin@test.com
Password : password123
Role     : admin
```

## Customer

```text
Email    : customer@test.com
Password : password123
Role     : customer
```

## Customer 2

```text
Email    : customer2@test.com
Password : password123
Role     : customer
```

Seeder juga menyediakan beberapa product dan coupon untuk pengujian checkout.

Contoh product:

```text
TEST-LAPTOP-001
TEST-LAPTOP-002
TEST-MOUSE-001
TEST-KEYBOARD-001
TEST-INACTIVE-001
```

Contoh coupon:

```text
TEST10
TESTFIXED
TESTEXPIRED
TESTINACTIVE
```

---

# Running the Application

Jalankan Laravel development server:

```bash
php artisan serve
```

Default URL:

```text
http://127.0.0.1:8000
```

API menggunakan prefix:

```text
/api
```

---

# Authentication

Authentication menggunakan Laravel Sanctum.

Setelah login berhasil, API akan mengembalikan token:

```json
{
    "message": "Login successful",
    "data": {
        "user": {},
        "token": "..."
    }
}
```

Gunakan token tersebut pada request protected:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

---

# API Endpoints

## Authentication

| Method | Endpoint        | Access        | Description      |
| ------ | --------------- | ------------- | ---------------- |
| POST   | `/api/register` | Public        | Register user    |
| POST   | `/api/login`    | Public        | Login            |
| POST   | `/api/logout`   | Authenticated | Logout           |
| GET    | `/api/me`       | Authenticated | Get current user |

---

## Products

| Method | Endpoint             | Access        | Description    |
| ------ | -------------------- | ------------- | -------------- |
| GET    | `/api/products`      | Authenticated | List products  |
| GET    | `/api/products/{id}` | Authenticated | Product detail |
| POST   | `/api/products`      | Admin         | Create product |
| PUT    | `/api/products/{id}` | Admin         | Update product |
| DELETE | `/api/products/{id}` | Admin         | Delete product |

Product listing mendukung:

```text
?search=laptop
?is_active=1
?min_price=100000
?max_price=500000
?sort=price
?direction=desc
?per_page=10
```

Contoh:

```http
GET /api/products?search=laptop&sort=price&direction=asc
```

---

## Orders

| Method | Endpoint                  | Access        | Description  |
| ------ | ------------------------- | ------------- | ------------ |
| GET    | `/api/orders`             | Authenticated | List orders  |
| POST   | `/api/orders`             | Authenticated | Create order |
| GET    | `/api/orders/{id}`        | Authenticated | Order detail |
| POST   | `/api/orders/{id}/cancel` | Owner/Admin   | Cancel order |
| POST   | `/api/orders/{id}/pay`    | Admin         | Pay order    |

---

## Coupons

| Method    | Endpoint            | Access | Description   |
| --------- | ------------------- | ------ | ------------- |
| GET       | `/api/coupons`      | Admin  | List coupons  |
| POST      | `/api/coupons`      | Admin  | Create coupon |
| GET       | `/api/coupons/{id}` | Admin  | Coupon detail |
| PUT/PATCH | `/api/coupons/{id}` | Admin  | Update coupon |
| DELETE    | `/api/coupons/{id}` | Admin  | Delete coupon |

---

# Checkout

Create order:

```http
POST /api/orders
```

Headers:

```http
Authorization: Bearer CUSTOMER_TOKEN
Accept: application/json
Content-Type: application/json
```

Request body:

```json
{
    "idempotency_key": "checkout-user-10-001",
    "coupon_code": "TEST10",
    "items": [
        {
            "product_id": 1,
            "quantity": 3
        },
        {
            "product_id": 2,
            "quantity": 2
        }
    ]
}
```

## Checkout Validation

Checkout memastikan:

* Item tidak kosong
* Quantity minimal 1
* Product harus tersedia
* Product harus aktif
* Product yang sudah di-soft-delete tidak dapat dibeli
* Stock harus mencukupi
* Duplicate product pada request digabungkan

Contoh:

```json
{
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        },
        {
            "product_id": 1,
            "quantity": 3
        }
    ]
}
```

Diproses sebagai:

```text
product_id = 1
quantity   = 5
```

---

# Product Discount Calculation

Untuk setiap item:

```text
gross_amount = price × quantity

discount_amount =
gross_amount × discount_percent ÷ 100

line_total =
gross_amount − discount_amount
```

Perhitungan diskon menggunakan pembulatan matematika ke rupiah terdekat.

Harga product selalu diambil dari database dan tidak mempercayai harga yang dikirim oleh client.

---

# Coupon

Coupon mendukung:

* Percentage discount
* Fixed discount
* Maximum discount
* Minimum purchase
* Global usage limit
* Per-customer usage limit
* Start date
* Expiration date
* Active/inactive status

Percentage coupon:

```text
coupon_discount =
total_after_product_discount × percentage ÷ 100
```

Jika terdapat `max_discount`, nilai diskon dibatasi oleh maximum discount.

Fixed coupon:

```text
coupon_discount = value
```

Nilai fixed coupon tidak dapat membuat taxable amount menjadi negatif.

---

# Shipping

Shipping menggunakan aturan:

```text
Taxable amount >= Rp500.000
→ Shipping = Rp0

Taxable amount < Rp500.000
→ Shipping = Rp25.000
```

---

# Tax

Tax sebesar 11% dihitung dari nilai setelah product discount dan coupon discount.

```text
taxable_amount =
subtotal - product_discount - coupon_discount

tax_amount =
taxable_amount × 11%
```

Shipping tidak termasuk taxable amount.

---

# Grand Total

```text
grand_total =
taxable_amount + tax_amount + shipping_cost
```

Semua nilai uang disimpan sebagai integer rupiah menggunakan database integer type, bukan floating point.

---

# Stock Management

Stock dikurangi ketika checkout berhasil dibuat.

Setiap product dalam order menghasilkan satu stock movement.

Contoh:

```text
stock_before = 10
quantity     = 3
stock_after  = 7
```

Stock movement checkout dicatat dengan:

```text
type = checkout
```

---

# Overselling Prevention

Overselling dicegah menggunakan kombinasi:

1. Database transaction
2. `lockForUpdate()` pada product
3. Stock validation sebelum deduction
4. Database constraints

Saat checkout, product yang akan diproses diambil menggunakan row-level lock:

```php
Product::query()
    ->whereIn('id', $productIds)
    ->where('is_active', true)
    ->lockForUpdate()
    ->get();
```

Dengan row locking, request checkout yang berjalan secara bersamaan tidak dapat membaca dan mengubah stock product secara bebas pada waktu yang sama.

Seluruh proses checkout berada di dalam:

```php
DB::transaction(...)
```

Jika salah satu product gagal diproses, transaction akan di-rollback sehingga perubahan order, stock, dan stock movement tidak tersimpan sebagian.

---

# Idempotency

Setiap checkout wajib memiliki:

```text
idempotency_key
```

Database menggunakan unique constraint:

```text
(user_id, idempotency_key)
```

Artinya idempotency key hanya unique untuk setiap customer.

Contoh:

```text
Customer A + checkout-001 → Order A
Customer A + checkout-001 → Order A yang sama

Customer B + checkout-001 → Order B
```

Ketika request dengan kombinasi `user_id` dan `idempotency_key` sudah pernah berhasil dibuat, service akan mengambil order sebelumnya dan tidak menjalankan checkout kembali.

Dengan demikian:

* Tidak dibuat order duplicate
* Stock tidak dikurangi dua kali
* Order item tidak dibuat dua kali
* Request kedua mengembalikan order sebelumnya

---

# Order Ownership

Customer hanya dapat melihat order miliknya sendiri.

Query order untuk customer dibatasi berdasarkan:

```text
user_id
```

Admin dapat melihat seluruh order.

Customer juga tidak dapat mengakses detail order milik customer lain.

---

# Order Cancellation

Order hanya dapat dibatalkan jika:

```text
status = pending_payment
```

dan order belum melewati batas 30 menit.

Ketika cancellation berhasil:

```text
status        → cancelled
cancelled_at  → current timestamp
stock         → restored
```

Stock movement baru dibuat dengan:

```text
type = cancellation
```

Order yang sudah cancelled tidak dapat dibatalkan kembali, sehingga stock tidak dikembalikan dua kali.

Order yang sudah paid tidak dapat dibatalkan.

Order yang melewati batas 30 menit akan menjadi:

```text
expired
```

dan tidak dapat dibatalkan.

Coupon yang digunakan pada order cancelled kembali dianggap tersedia karena order dengan status `cancelled` tidak dihitung dalam penggunaan coupon.

---

# Payment

Payment hanya dapat dilakukan oleh admin.

Order harus berada pada:

```text
pending_payment
```

Ketika payment berhasil:

```text
status   → paid
paid_at  → current timestamp
```

Stock tidak dikurangi kembali saat payment karena stock sudah dikurangi ketika checkout.

Pemanggilan payment pada order yang sudah `paid` tidak menjalankan proses payment kedua kali.

Order `cancelled` atau `expired` tidak dapat dibayar.

---

# Architecture

Project menggunakan pemisahan sederhana antara HTTP layer dan business logic.

Struktur utama:

```text
Request
   ↓
Controller
   ↓
Service
   ↓
Model / Database
```

## Form Request

Validasi request dilakukan menggunakan Form Request.

Contoh:

```text
app/Http/Requests/StoreOrderRequest.php
```

Form Request bertanggung jawab untuk validasi input checkout sebelum masuk ke business logic.

## Controller

Controller bertanggung jawab menangani HTTP request dan response.

Contoh:

```text
app/Http/Controllers/OrderController.php
```

Controller tidak menangani seluruh business logic checkout secara langsung.

## Service

Business logic order ditempatkan pada:

```text
app/Services/OrderService.php
```

Service menangani:

* Checkout
* Pricing
* Product discount
* Coupon
* Stock deduction
* Stock movement
* Idempotency
* Cancellation
* Payment
* Order access

Pendekatan ini membuat controller tetap tipis dan business logic lebih mudah diuji.

---

# Database Design

Entity utama:

```text
users
products
coupons
orders
order_items
stock_movements
personal_access_tokens
```

Relasi utama:

```text
User
 └── hasMany Orders

Order
 ├── belongsTo User
 ├── hasMany OrderItems
 └── belongsTo Coupon

OrderItem
 ├── belongsTo Order
 └── belongsTo Product

StockMovement
 ├── belongsTo Product
 └── belongsTo Order

Product
 └── SoftDeletes
```

Database juga menggunakan constraint seperti:

* Unique product SKU
* Unique coupon code
* Unique `(user_id, idempotency_key)`
* Foreign key relationships
* Restrict deletion untuk product yang memiliki order item

---

# Automated Testing

For a separate testing database, configure .env.testing:

```bash
APP_ENV=testing
DB_CONNECTION=mysql
DB_DATABASE=order_inventory_test
```

```bash
php artisan migrate --env=testing
```

```bash
php artisan test
```

Atau:

```bash
php artisan test --testsuite=Feature
```

Test mencakup area seperti:

```text
Authentication
Order creation
Order calculation
Product discount
Coupon
Idempotency
Stock management
Order cancellation
Payment
Authorization
Order ownership
Concurrency
```

Test menggunakan database testing dan `RefreshDatabase` agar setiap test mendapatkan database state yang bersih.

---

# Postman

Collection dan environment tersedia di folder `docs/postman/`:

```text
docs/postman/Laravel Order Inventory - Local.postman_environment.json
docs/postman/Laravel Order Inventory API.postman_collection.json
```

## Cara Pakai

1. Jalankan `php artisan migrate:fresh --seed` lalu `php artisan serve`.
2. Import kedua file di Postman (Import → drag file).
3. Pilih environment **Laravel Order Inventory API - Local** di pojok kanan atas.
4. Sesuaikan `base_url` jika server tidak berjalan di `http://127.0.0.1:8000`.
5. Jalankan **1. Auth → [ADMIN] Login** dan **[CUSTOMER] Login**. Token tersimpan otomatis ke environment.

## Struktur Collection

| Folder | Isi |
| --- | --- |
| 1. Auth | Register, login, me, logout |
| 2. Products | CRUD admin, list/detail admin dan customer, negative test role |
| 3. Coupons | CRUD admin, negative test role |
| 4. Orders & Checkout | Checkout, cancel, pay, negative test validasi |

Label pada nama request:

* `[ADMIN]` memakai `{{admin_token}}`
* `[CUSTOMER]` memakai `{{customer_token}}`
* `[PUBLIC]` / `[NO TOKEN]` tanpa token
* Akhiran `-> 401`, `-> 403`, `-> 422` menandakan negative test

## Urutan Testing

Auth → Create Product → Create Coupon → Checkout → Pay → Cancel.
Delete product dan coupon dijalankan paling akhir.tion: Bearer YOUR_TOKEN
```

---

# Example Checkout Calculation

Product A:

```text
Price            : Rp150.000
Quantity         : 3
Product Discount : 10%

Gross Amount     : Rp450.000
Discount         : Rp45.000
Line Total       : Rp405.000
```

Product B:

```text
Price            : Rp100.000
Quantity         : 2
Product Discount : 0%

Gross Amount     : Rp200.000
Discount         : Rp0
Line Total       : Rp200.000
```

Summary:

```text
Subtotal                     : Rp650.000
Product Discount             : Rp45.000
After Product Discount       : Rp605.000
```

Coupon:

```text
Type                         : Percentage
Value                        : 10%
Maximum Discount             : Rp40.000
Calculated Discount          : Rp60.500
Applied Discount             : Rp40.000
```

Final calculation:

```text
Taxable Amount               : Rp565.000
Tax 11%                      : Rp62.150
Shipping                     : Rp0
Grand Total                  : Rp627.150
```

---

# Technical Decisions & Assumptions

## Money

Money is stored as integer rupiah instead of floating point to avoid floating-point precision issues.

## Product Price

Checkout always uses the current price stored in the database. Client-submitted price is not trusted.

## Product Deletion

Products use soft deletion so historical order items can retain their product information.

## Stock Timing

Stock is deducted during checkout/order creation rather than during payment.

This prevents multiple unpaid orders from reserving the same available stock.

## Order Expiration

An unpaid order older than 30 minutes is considered expired when cancellation or payment is attempted.

## Coupon Usage

Cancelled orders are excluded from coupon usage calculations so that the coupon quota can become available again.

## Idempotency Scope

Idempotency is scoped per customer using:

```text
user_id + idempotency_key
```

The same idempotency key may therefore be used by different customers.

## Authorization

Customers can access only their own orders, while administrators can access all orders and perform administrative operations such as payment.

---

# Development

Run Laravel development server:

```bash
php artisan serve
```

Run tests:

```bash
php artisan test
```

Check routes:

```bash
php artisan route:list --path=api
```

---

# License

This project was created for technical assessment and demonstration purposes.
