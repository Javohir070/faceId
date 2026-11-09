<?php

namespace App\Services;

use App\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use App\Models\Device;
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

    /**
     * Kategoriya belgisi bosilganda o‘ngdan ochiladigan qurilmalar paneli.
     * AssignedDevicesScope ishlaydi: foydalanuvchi faqat o‘z qurilmalarini ko‘radi.
     *
     * @param  string|null  $key  "category:5"
     * @return array<string, mixed>|null
     */
    public function devicesPanel(?string $key): ?array
    {
        if ($key === null || preg_match('/^category:(\d+)$/', $key, $matches) !== 1) {
            return null;
        }

        $category = Category::query()->find((int) $matches[1]);

        if ($category === null) {
            return null;
        }

        $devices = $category->devices()
            ->withCount([
                'employees',
                'employees as synced_employees_count' => fn ($employees) => $employees->whereNotNull('device_employee.synced_at'),
            ])
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'ip_address' => $device->ip_address,
                'port' => $device->port,
                'type_label' => $device->typeLabel(),
                'is_online' => (bool) $device->is_online,
                'last_ping_at' => $device->last_ping_at?->format('Y-m-d H:i'),
                'employees_count' => $device->employees_count,
                'synced_employees_count' => $device->synced_employees_count,
            ])
            ->values();

        return [
            'key' => $key,
            'title' => $category->name,
            'total' => $devices->count(),
            'devices' => $devices->all(),
            'filter' => ['category_id' => $category->id],
        ];
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
