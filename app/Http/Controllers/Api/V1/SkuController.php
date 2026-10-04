<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\ProductCache;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreSkuRequest;
use App\Http\Requests\Catalog\UpdateSkuRequest;
use App\Http\Resources\SkuResource;
use App\Models\Product;
use App\Models\Sku;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Group('Catalog', weight: 2)]
#[Authorize('admin')]
class SkuController extends Controller
{
    public function store(StoreSkuRequest $request, Product $product, ProductCache $cache): JsonResponse
    {
        $sku = $product->skus()->create([
            'attrs' => $request->input('attrs'),
            'price_cents' => $request->integer('price_cents'),
            'currency' => $request->string('currency')->toString(),
            'stock' => $request->integer('stock'),
            'version' => 0,
        ]);

        $cache->flush();

        return SkuResource::make($sku)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSkuRequest $request, Sku $sku, ProductCache $cache): SkuResource
    {
        $sku->fill([
            'attrs' => $request->input('attrs'),
            'price_cents' => $request->integer('price_cents'),
            'currency' => $request->string('currency')->toString(),
            'stock' => $request->integer('stock'),
        ]);
        $sku->save();
        $cache->flush();

        return SkuResource::make($sku);
    }

    public function destroy(Sku $sku, ProductCache $cache): JsonResponse
    {
        $sku->delete();
        $cache->flush();

        return response()->json(null, 204);
    }
}
