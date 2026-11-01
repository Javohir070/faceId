<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Kategoriya qo‘shish/tahrirlash alohida sahifa emas — ro‘yxatdagi o‘ng panel (Drawer).
 */
class CategoryDrawerTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_old_create_and_edit_pages_open_the_drawer_on_the_list(): void
    {
        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['categories']]);
        $category = Category::factory()->create(['name' => 'Kirish']);

        $this->actingAs($admin)
            ->get(route('admin.categories.create'))
            ->assertRedirect(route('admin.categories.index', ['create' => 1]));

        $this->actingAs($admin)
            ->get(route('admin.categories.edit', $category))
            ->assertRedirect(route('admin.categories.index', ['edit' => $category->id]));

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['create' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Categories/Index')
                ->where('drawer.mode', 'create'));

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['edit' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('drawer.mode', 'edit')
                ->where('drawer.record.id', $category->id)
                ->where('drawer.record.name', 'Kirish'));

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['edit' => 999999]))
            ->assertInertia(fn (Assert $page) => $page->where('drawer', null));
    }

    public function test_show_page_is_gone_and_old_link_returns_to_the_list(): void
    {
        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['categories']]);
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $category))
            ->assertRedirect(route('admin.categories.index'));
    }

    public function test_validation_error_returns_to_the_list(): void
    {
        $admin = $this->createUserWithPermissions([config('menu_permissions.resources')['categories']]);

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasErrors(['name' => 'Kategoriya nomini kiriting.']);
    }
}
