<?php

namespace Tests\Feature\Admin;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeeFaceThumbnailTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_face_comes_back_as_a_small_square_jpeg(): void
    {
        $employee = $this->employeeWithFace(Department::factory()->create());

        $response = $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.employees.face', $employee))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        [$width, $height, $type] = getimagesizefromstring($response->getContent());

        $this->assertSame([240, 240, IMAGETYPE_JPEG], [$width, $height, $type]);
        $this->assertLessThan(40 * 1024, strlen($response->getContent()));
        $this->assertStringContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }

    public function test_employee_without_a_face_has_no_photo(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->createUserWithPermissions())
            ->get(route('admin.employees.face', $employee))
            ->assertNotFound();
    }

    public function test_photo_of_another_departments_employee_is_hidden(): void
    {
        $employee = $this->employeeWithFace(Department::factory()->create());
        $user = $this->createDepartmentUserWithPermissions(Department::factory()->create()->id);

        $this->actingAs($user)
            ->get(route('admin.employees.face', $employee))
            ->assertNotFound();
    }

    public function test_photo_needs_the_employees_permission(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeWithFace($department);
        $user = $this->createDepartmentUserWithPermissions($department->id, [config('menu_permissions.resources')['groups']]);

        $this->actingAs($user)
            ->get(route('admin.employees.face', $employee))
            ->assertForbidden();
    }

    private function employeeWithFace(Department $department): Employee
    {
        $image = imagecreatetruecolor(400, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        ob_start();
        imagejpeg($image, null, 90);

        $employee = Employee::factory()->create(['department_id' => $department->id]);
        $employee->face()->create(['face' => base64_encode((string) ob_get_clean())]);

        return $employee;
    }
}
