<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ProductService
{
    public function getProducts(
        User $user,
        Request $request
    ): LengthAwarePaginator {
        $query = Product::query();

        if ($user->role !== 'admin') {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($query) use ($search) {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (
            $user->role === 'admin'
            && $request->filled('is_active')
        ) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        if ($request->filled('min_price')) {
            $query->where(
                'price',
                '>=',
                $request->integer('min_price')
            );
        }

        if ($request->filled('max_price')) {
            $query->where(
                'price',
                '<=',
                $request->integer('max_price')
            );
        }

        $allowedSorts = [
            'price',
            'name',
            'stock',
            'created_at',
        ];

        $sort = $request->get('sort', 'created_at');

        $direction = $request->get('direction', 'asc');

        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy(
                $sort,
                $direction === 'desc' ? 'desc' : 'asc'
            );
        }

        $perPage = min(
            max($request->integer('per_page', 10), 1),
            100
        );

        return $query->paginate($perPage);
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(
        Product $product,
        array $data
    ): Product {
        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}