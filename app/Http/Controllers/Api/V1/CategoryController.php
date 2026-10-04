<?php

namespace App\Http\Controllers\Api\V1;

use App\Catalog\ProductCache;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Catalog', weight: 2)]
class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->orderBy('sort')->orderBy('id')->get(),
        );
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = new Category;
        $category->ensureParentIsValid($request->integer('parent_id') ?: null);
        $category->fill([
            'name' => $request->string('name')->toString(),
            'slug' => $request->filled('slug') ? $request->string('slug')->toString() : null,
            'parent_id' => $request->integer('parent_id') ?: null,
            'sort' => $request->integer('sort'),
        ]);
        $category->save();

        return CategoryResource::make($category)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category, ProductCache $cache): CategoryResource
    {
        $parentId = $request->exists('parent_id')
            ? ($request->input('parent_id') === null ? null : $request->integer('parent_id'))
            : ($category->parent_id !== null ? (int) $category->parent_id : null);

        $category->ensureParentIsValid($parentId);
        $category->fill([
            'name' => $request->string('name')->toString(),
            'parent_id' => $parentId,
            'sort' => $request->integer('sort', $category->sort),
        ]);

        if ($request->filled('slug')) {
            $category->slug = $request->string('slug')->toString();
        }

        $category->save();
        $cache->flush();

        return CategoryResource::make($category);
    }

    public function destroy(Category $category, ProductCache $cache): JsonResponse
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            return response()->json([
                'message' => 'Category still has products or child categories.',
            ], 409);
        }

        $category->delete();
        $cache->flush();

        return response()->json(null, 204);
    }
}
