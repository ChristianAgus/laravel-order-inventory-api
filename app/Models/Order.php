<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'order_number',
    'user_id',
    'idempotency_key',
    'status',
    'subtotal',
    'product_discount',
    'coupon_discount',
    'taxable_amount',
    'tax_amount',
    'shipping_cost',
    'grand_total',
    'coupon_id',
    'paid_at',
    'cancelled_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'product_discount' => 'integer',
            'coupon_discount' => 'integer',
            'taxable_amount' => 'integer',
            'tax_amount' => 'integer',
            'shipping_cost' => 'integer',
            'grand_total' => 'integer',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}