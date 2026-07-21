<?php

namespace App\Services;

use App\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->categoryRepository->paginate($perPage);
    }

    public function allOrdered(): Collection
    {
        return $this->categoryRepository->allOrdered();
    }

    public function create(array $data): Category
    {
        return DB::transaction(fn (): Category => $this->categoryRepository->create($data));
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(fn (): Category => $this->categoryRepository->update($category, $data));
    }

    public function delete(Category $category): ?string
    {
        if ($this->categoryRepository->hasDevices($category)) {
            return 'Kategoriyaga biriktirilgan qurilmalar bor. Avval qurilmalarni o\'chiring yoki boshqa kategoriyaga o\'tkazing.';
        }

        DB::transaction(function () use ($category): void {
            $this->categoryRepository->delete($category);
        });

        return null;
    }
}
