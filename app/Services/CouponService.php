<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CouponService
{
    public function getCoupons(int $perPage = 10): LengthAwarePaginator
    {
        $perPage = min(
            max($perPage, 1),
            100
        );

        return Coupon::query()
            ->select([
                'id',
                'code',
                'type',
                'value',
                'max_discount',
                'minimum_purchase',
                'usage_limit',
                'usage_per_customer',
                'starts_at',
                'expires_at',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Coupon
    {
        if ($data['type'] === 'fixed') {
            $data['max_discount'] = null;
        }

        return Coupon::create($data);
    }

    public function update(
        Coupon $coupon,
        array $data
    ): Coupon {
        if ($data['type'] === 'fixed') {
            $data['max_discount'] = null;
        }

        $coupon->update($data);

        return $coupon->fresh();
    }

    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }
}