<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\GeneratesUniqueSlug;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ProductStatus $status
 */
#[Fillable(['category_id', 'title', 'slug', 'description', 'status'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use GeneratesUniqueSlug, HasFactory;

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug((string) $product->title);
            }
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Sku, $this>
     */
    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class)->orderBy('id');
    }
}
