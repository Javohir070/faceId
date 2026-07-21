<?php

namespace App\Repositories;

use App\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('devices')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function allOrdered(): Collection
    {
        return Category::query()
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->fresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function hasDevices(Category $category): bool
    {
        return $category->devices()->exists();
    }
}
