<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        private CouponService $couponService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $coupons = $this->couponService->getCoupons(
            $request->integer('per_page', 10)
        );

        return response()->json($coupons);
    }

    public function store(
        StoreCouponRequest $request
    ): JsonResponse {
        $coupon = $this->couponService->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Coupon created successfully',
            'data' => $coupon,
        ], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json([
            'data' => $coupon,
        ]);
    }

    public function update(
        UpdateCouponRequest $request,
        Coupon $coupon
    ): JsonResponse {
        $coupon = $this->couponService->update(
            $coupon,
            $request->validated()
        );

        return response()->json([
            'message' => 'Coupon updated successfully',
            'data' => $coupon,
        ]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->couponService->delete($coupon);

        return response()->json([
            'message' => 'Coupon deleted successfully',
        ]);
    }
}