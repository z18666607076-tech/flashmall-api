<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\ProductCache;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListProductsRequest;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Group('Catalog', weight: 2)]
#[Authorize('admin', only: ['store', 'update', 'destroy'])]
class ProductController extends Controller
{
    public function index(ListProductsRequest $request, ProductCache $cache): AnonymousResourceCollection
    {
        $perPage = min(100, max(1, $request->integer('per_page', 15)));
        $page = max(1, $request->integer('page', 1));
        $categoryId = $request->filled('category_id') ? $request->integer('category_id') : null;
        $search = $request->filled('q') ? $request->string('q')->trim()->toString() : null;

        $paginator = $cache->rememberPage(
            hash('sha256', json_encode([$categoryId, $search, $page, $perPage], JSON_THROW_ON_ERROR)),
            $perPage,
            $page,
            function () use ($categoryId, $search, $perPage, $page) {
                $query = Product::query()
                    ->published()
                    ->with(['category', 'skus'])
                    ->orderByDesc('id');

                if ($categoryId !== null) {
                    $query->where('category_id', $categoryId);
                }

                if ($search !== null && $search !== '') {
                    $query->where('title', 'like', '%'.$this->escapeLike($search).'%');
                }

                return $query->paginate($perPage, ['*'], 'page', $page);
            },
        );

        return ProductResource::collection($paginator);
    }

    public function show(int $id, ProductCache $cache): ProductResource
    {
        $product = $cache->rememberProduct($id, function () use ($id): Product {
            return Product::query()
                ->published()
                ->with(['category', 'skus'])
                ->findOrFail($id);
        });

        return ProductResource::make($product);
    }

    public function store(StoreProductRequest $request, ProductCache $cache): JsonResponse
    {
        $product = Product::query()->create([
            'category_id' => $request->integer('category_id'),
            'title' => $request->string('title')->toString(),
            'slug' => $request->filled('slug') ? $request->string('slug')->toString() : null,
            'description' => $request->input('description'),
            'status' => $request->enum('status', ProductStatus::class),
        ]);

        $cache->flush();

        return ProductResource::make($product->load(['category', 'skus']))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product, ProductCache $cache): ProductResource
    {
        $product->fill([
            'category_id' => $request->integer('category_id'),
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'status' => $request->enum('status', ProductStatus::class),
        ]);

        if ($request->filled('slug')) {
            $product->slug = $request->string('slug')->toString();
        }

        $product->save();
        $cache->flush();

        return ProductResource::make($product->load(['category', 'skus']));
    }

    public function destroy(Product $product, ProductCache $cache): JsonResponse
    {
        $product->delete();
        $cache->flush();

        return response()->json(null, 204);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
