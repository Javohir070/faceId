<?php

namespace App\Http\Controllers;

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
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => $this->categoryService->paginate(10),
            'drawer' => $this->drawerState($request),
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

    /** Alohida ko‘rish sahifasi yo‘q — nomi va qurilmalar soni ro‘yxatda; eski havola ro‘yxatga qaytaradi. */
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

    /**
     * ?create=1 yoki ?edit={id} — eski create/edit havolalari panelni ochib keladi.
     *
     * @return array{mode: string, category?: array{id: int, name: string}}|null
     */
    private function drawerState(Request $request): ?array
    {
        if ($request->boolean('create')) {
            return ['mode' => 'create'];
        }

        $category = $request->integer('edit')
            ? Category::query()->find($request->integer('edit'), ['id', 'name'])
            : null;

        return $category === null ? null : ['mode' => 'edit', 'category' => $category->only(['id', 'name'])];
    }
}
