<?php

namespace App\Interfaces;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface
{
    public function paginate(int $perPage = 20): LengthAwarePaginator;

    public function allOrdered(): Collection;

    public function create(array $data): Category;

    public function update(Category $category, array $data): Category;

    public function delete(Category $category): void;

    public function hasDevices(Category $category): bool;
}
