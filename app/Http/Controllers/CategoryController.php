<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OpensFormDrawer;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use OpensFormDrawer;

    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => $this->categoryService->paginate(20),
            'drawer' => $this->drawerState($request, 'categories', fn (int $id): ?array => Category::query()->find($id, ['id', 'name'])?->only(['id', 'name'])),
        ]);
    }

    /** Alohida sahifa yo‘q: qo‘shish oynasi ro‘yxatdagi o‘ng panelda ochiladi. */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.categories.index', ['create' => 1]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Kategoriya muvaffaqiyatli yaratildi.');
    }

    /** Alohida ko‘rish sahifasi yo‘q — ma’lumot ro‘yxatda; eski havola ro‘yxatga qaytaradi. */
    public function show(Category $category): RedirectResponse
    {
        return redirect()->route('admin.categories.index');
    }

    public function edit(Category $category): RedirectResponse
    {
        return redirect()->route('admin.categories.index', ['edit' => $category->id]);
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
