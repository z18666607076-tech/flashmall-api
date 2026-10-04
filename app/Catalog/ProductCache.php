<?php

namespace App\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sku;
use Closure;
use Illuminate\Cache\TaggedCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ProductCache
{
    /**
     * @param  Closure(): Product  $resolver
     */
    public function rememberProduct(int $id, Closure $resolver): Product
    {
        $key = "product:{$id}";
        $store = $this->store();
        $cached = $store->get($key);

        if (is_array($cached)) {
            $store->touch($key, $this->ttl());

            return $this->restoreProduct($cached);
        }

        $product = $resolver();
        $store->put($key, $this->snapshot($product), $this->ttl());

        return $product;
    }

    /**
     * @param  Closure(): LengthAwarePaginator<int, Product>  $resolver
     * @return LengthAwarePaginator<int, Product>
     */
    public function rememberPage(string $key, int $perPage, int $page, Closure $resolver): LengthAwarePaginator
    {
        $cacheKey = "products:index:{$key}";
        $store = $this->store();
        $cached = $store->get($cacheKey);

        if (is_array($cached) && isset($cached['items'], $cached['total'])) {
            $store->touch($cacheKey, $this->ttl());

            return $this->restorePage($cached, $perPage, $page);
        }

        $paginator = $resolver();
        $store->put($cacheKey, [
            'total' => $paginator->total(),
            'items' => $paginator->getCollection()
                ->map(fn (Product $product): array => $this->snapshot($product))
                ->values()
                ->all(),
        ], $this->ttl());

        return $paginator;
    }

    public function flush(): void
    {
        $this->store()->flush();
    }

    /**
     * @return array{product: array<string, mixed>, category: array<string, mixed>|null, skus: list<array<string, mixed>>}
     */
    private function snapshot(Product $product): array
    {
        $category = $product->relationLoaded('category') ? $product->getRelation('category') : null;

        return [
            'product' => $product->getAttributes(),
            'category' => $category instanceof Category ? $category->getAttributes() : null,
            'skus' => $product->relationLoaded('skus')
                ? $product->skus
                    ->map(fn (Sku $sku): array => $sku->getAttributes())
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function restoreProduct(array $cached): Product
    {
        $attributes = $cached['product'] ?? null;

        if (! is_array($attributes)) {
            throw new RuntimeException('Cached product payload is invalid.');
        }

        $product = (new Product)->newFromBuilder($attributes);

        $categoryAttributes = $cached['category'] ?? null;

        if (is_array($categoryAttributes)) {
            $product->setRelation('category', (new Category)->newFromBuilder($categoryAttributes));
        }

        $skus = [];

        foreach ($cached['skus'] ?? [] as $row) {
            if (is_array($row)) {
                $skus[] = (new Sku)->newFromBuilder($row);
            }
        }

        $product->setRelation('skus', (new Sku)->newCollection($skus));

        return $product;
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return LengthAwarePaginator<int, Product>
     */
    private function restorePage(array $cached, int $perPage, int $page): LengthAwarePaginator
    {
        $items = [];

        foreach ($cached['items'] ?? [] as $row) {
            if (is_array($row)) {
                $items[] = $this->restoreProduct($row);
            }
        }

        return new LengthAwarePaginator(
            $items,
            (int) ($cached['total'] ?? 0),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    private function store(): TaggedCache
    {
        $repository = Cache::store();

        if (! $repository->supportsTags()) {
            throw new RuntimeException('Catalog caching requires a taggable cache store such as Redis.');
        }

        return $repository->tags([(string) config('catalog.cache_tag', 'catalog')]);
    }

    private function ttl(): int
    {
        return max(1, (int) config('catalog.cache_ttl', 600));
    }
}
