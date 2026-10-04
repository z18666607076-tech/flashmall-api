<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['parent_id', 'name', 'slug', 'sort'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use GeneratesUniqueSlug, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug((string) $category->name);
            }
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function ensureParentIsValid(?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($this->exists && $parentId === $this->id) {
            throw ValidationException::withMessages([
                'parent_id' => ['A category cannot be nested under itself.'],
            ]);
        }

        $current = $parentId;
        $guard = 0;

        while ($current !== null) {
            if ($this->exists && $current === $this->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A category cannot be nested under its own descendant.'],
                ]);
            }

            $next = static::query()->whereKey($current)->value('parent_id');
            $current = $next === null ? null : (int) $next;
            $guard++;

            if ($guard > 50) {
                break;
            }
        }
    }
}
