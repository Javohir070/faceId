<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => $this->categoryService->paginate(10),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Form');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategoriya muvaffaqiyatli yaratildi.');
    }

    public function show(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'created_at' => $category->created_at,
                'devices_count' => $category->devices()->count(),
            ],
        ]);
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => $category->only(['id', 'name']),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->update($category, $request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategoriya muvaffaqiyatli yangilandi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($error = $this->categoryService->delete($category)) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategoriya muvaffaqiyatli o\'chirildi.');
    }
}
