<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->getOrders(
            $request->user(),
            $request->integer('per_page', 10)
        );

        return response()->json($orders);
    }

    public function store(
        StoreOrderRequest $request
    ): JsonResponse {
        $order = $this->orderService->createOrder(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order,
        ], 201);
    }

    public function show(
        Request $request,
        Order $order
    ): JsonResponse {
        $order = $this->orderService->getOrder(
            $request->user(),
            $order
        );

        return response()->json([
            'data' => $order,
        ]);
    }

    public function cancel(
        Request $request,
        Order $order
    ): JsonResponse {
        $order = $this->orderService->cancelOrder(
            $request->user(),
            $order
        );

        return response()->json([
            'message' => 'Order cancelled successfully',
            'data' => $order,
        ]);
    }

    public function pay(
        Order $order
    ): JsonResponse {
        $order = $this->orderService->payOrder($order);

        return response()->json([
            'message' => 'Order paid successfully',
            'data' => $order,
        ]);
    }
}