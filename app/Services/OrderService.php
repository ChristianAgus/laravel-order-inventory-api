<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createOrder(
        User $user,
        array $data
    ): Order {
        return DB::transaction(function () use ($user, $data) {
            /*
             * Idempotency:
             * Request yang sama dari user yang sama
             * harus mengembalikan order sebelumnya.
             */
            $existingOrder = Order::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existingOrder) {
                return $existingOrder->load([
                    'items',
                    'coupon',
                ]);
            }

            /*
             * Gabungkan duplicate product_id.
             *
             * Contoh:
             * product 1 qty 2
             * product 1 qty 3
             *
             * menjadi:
             * product 1 qty 5
             */
            $items = collect($data['items'])
                ->groupBy('product_id')
                ->map(function ($items, $productId) {
                    return [
                        'product_id' => (int) $productId,
                        'quantity' => $items->sum('quantity'),
                    ];
                })
                ->values();

            $productIds = $items
                ->pluck('product_id')
                ->all();

            /*
             * Lock semua product yang akan diproses.
             *
             * orderBy id digunakan supaya request concurrent
             * mengunci row dalam urutan yang konsisten.
             */
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($productIds)) {
                throw ValidationException::withMessages([
                    'items' => 'One or more products are unavailable.',
                ]);
            }

            $subtotal = 0;
            $productDiscount = 0;
            $orderItems = [];

            foreach ($items as $item) {
                /** @var Product $product */
                $product = $products->get($item['product_id']);

                $quantity = $item['quantity'];

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for product {$product->name}.",
                    ]);
                }

                /*
                 * Harga selalu dari database.
                 * BUKAN dari request.
                 */
                $price = $product->price;

                $grossAmount = $price * $quantity;

                $discountAmount = (int) round(
                    $grossAmount
                    * $product->discount_percent
                    / 100
                );

                $lineTotal = $grossAmount - $discountAmount;

                $subtotal += $grossAmount;
                $productDiscount += $discountAmount;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'price' => $price,
                    'quantity' => $quantity,
                    'discount_percent' => $product->discount_percent,
                    'discount_amount' => $discountAmount,
                    'line_total' => $lineTotal,
                ];
            }

            $totalAfterProductDiscount =
                $subtotal - $productDiscount;

            /*
             * Coupon
             */
            $coupon = null;
            $couponDiscount = 0;

            if (! empty($data['coupon_code'])) {
                $coupon = Coupon::query()
                    ->where('code', strtoupper(trim($data['coupon_code'])))
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $coupon) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Invalid coupon.',
                    ]);
                }

                $now = now();

                if (
                    $now->lt($coupon->starts_at)
                    || $now->gt($coupon->expires_at)
                ) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Coupon is not active at this time.',
                    ]);
                }

                if (
                    $totalAfterProductDiscount
                    < $coupon->minimum_purchase
                ) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Minimum purchase requirement is not met.',
                    ]);
                }

                /*
                 * Global usage limit.
                 *
                 * cancelled order tidak dihitung.
                 */
                if ($coupon->usage_limit !== null) {
                    $globalUsage = Order::query()
                        ->where('coupon_id', $coupon->id)
                        ->where('status', '!=', 'cancelled')
                        ->count();

                    if ($globalUsage >= $coupon->usage_limit) {
                        throw ValidationException::withMessages([
                            'coupon_code' => 'Coupon usage limit has been reached.',
                        ]);
                    }
                }

                /*
                 * Usage per customer.
                 */
                $customerUsage = Order::query()
                    ->where('coupon_id', $coupon->id)
                    ->where('user_id', $user->id)
                    ->where('status', '!=', 'cancelled')
                    ->count();

                if (
                    $customerUsage >= $coupon->usage_per_customer
                ) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'You have reached the usage limit for this coupon.',
                    ]);
                }

                if ($coupon->type === 'percentage') {
                    $couponDiscount = (int) round(
                        $totalAfterProductDiscount
                        * $coupon->value
                        / 100
                    );

                    if ($coupon->max_discount !== null) {
                        $couponDiscount = min(
                            $couponDiscount,
                            $coupon->max_discount
                        );
                    }
                } else {
                    $couponDiscount = min(
                        $coupon->value,
                        $totalAfterProductDiscount
                    );
                }
            }

            $taxableAmount = max(
                $totalAfterProductDiscount - $couponDiscount,
                0
            );

            $taxAmount = (int) round(
                $taxableAmount * 11 / 100
            );

            $shippingCost = $taxableAmount >= 500000
                ? 0
                : 25000;

            $grandTotal =
                $taxableAmount
                + $taxAmount
                + $shippingCost;

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'idempotency_key' => $data['idempotency_key'],
                'status' => 'pending_payment',
                'subtotal' => $subtotal,
                'product_discount' => $productDiscount,
                'coupon_discount' => $couponDiscount,
                'taxable_amount' => $taxableAmount,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'coupon_id' => $coupon?->id,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create($item);

                $product = $products->get($item['product_id']);

                $stockBefore = $product->stock;
                $stockAfter = $stockBefore - $item['quantity'];

                $product->update([
                    'stock' => $stockAfter,
                ]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'type' => 'checkout',
                    'quantity' => $item['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'description' => "Stock deducted for order {$order->order_number}",
                ]);
            }

            return $order->load([
                'items',
                'coupon',
            ]);
        });
    }

    public function getOrders(
        User $user,
        int $perPage = 10
    ): LengthAwarePaginator {
        $perPage = min(max($perPage, 1), 100);

        $query = Order::query()
            ->with([
                'items',
                'coupon',
            ])
            ->latest();

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        return $query->paginate($perPage);
    }

    public function getOrder(
        User $user,
        Order $order
    ): Order {
        if (
            $user->role !== 'admin'
            && $order->user_id !== $user->id
        ) {
            throw ValidationException::withMessages([
                'order' => 'Order not found.',
            ]);
        }

        return $order->load([
            'items',
            'coupon',
            'user',
        ]);
    }

    public function cancelOrder(
        User $user,
        Order $order
    ): Order {
        $expired = false;

        $order = DB::transaction(function () use ($user, $order, &$expired) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->role !== 'admin' && $order->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'order' => 'Order not found.',
                ]);
            }

            if ($order->status === 'cancelled') {
                return $order->load('items');
            }

            if ($order->status !== 'pending_payment') {
                throw ValidationException::withMessages([
                    'order' => 'Order cannot be cancelled.',
                ]);
            }

            if ($order->created_at->lt(now()->subMinutes(30))) {
                $order->update([
                    'status' => 'expired',
                ]);

                $expired = true;

                return $order->fresh()->load('items');
            }

            $items = $order->items()
                ->orderBy('product_id')
                ->get();

            $products = Product::query()
                ->whereIn(
                    'id',
                    $items->pluck('product_id')->all()
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'order' => 'Product for this order no longer exists.',
                    ]);
                }

                $stockBefore = $product->stock;
                $stockAfter = $stockBefore + $item->quantity;

                $product->update([
                    'stock' => $stockAfter,
                ]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'order_id' => $order->id,
                    'type' => 'cancellation',
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'description' => "Stock restored for cancelled order {$order->order_number}",
                ]);
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return $order->fresh()->load('items');
        });

        if ($expired) {
            throw ValidationException::withMessages([
                'order' => 'Order has expired and cannot be cancelled.',
            ]);
        }

        return $order;
    }

    public function payOrder(Order $order): Order
    {
        $expired = false;

        $order = DB::transaction(function () use ($order, &$expired) {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === 'paid') {
                return $order;
            }

            if ($order->status !== 'pending_payment') {
                throw ValidationException::withMessages([
                    'order' => 'Order cannot be paid.',
                ]);
            }

            if ($order->created_at->lt(now()->subMinutes(30))) {
                $order->update([
                    'status' => 'expired',
                ]);

                $expired = true;

                return $order->fresh()->load([
                    'items',
                    'coupon',
                ]);
            }

            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            return $order->fresh()->load([
                'items',
                'coupon',
            ]);
        });

        if ($expired) {
            throw ValidationException::withMessages([
                'order' => 'Order has expired.',
            ]);
        }

        return $order;
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . now()->format('YmdHis') . '-' . strtoupper(
                Str::random(6)
            );
        } while (
            Order::query()
                ->where('order_number', $number)
                ->exists()
        );

        return $number;
    }
}