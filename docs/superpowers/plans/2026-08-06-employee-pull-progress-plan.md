# Xodim tortish: per-device progress va aniq xatolar — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the two timeout-prone, opaque-error "Xodim tortish" flows (bulk `Pull.jsx` page and single-device `Devices/Show.jsx` button) with one shared per-device JSON endpoint driven by a frontend progress-modal loop, mirroring the proven `admin.sync.transfer-one` pattern.

**Architecture:** One new endpoint `POST /admin/employees/pull-one` (`device_id` in, per-device JSON result out) backed by the existing, untouched `EmployeePullService::pullDevice()`. Both frontend pages call it — the bulk page in a sequential loop, the device page once — through a shared `ProgressModal` component and `employeePull.js` fetch helper, extracted alongside the `Sync` page's existing (currently duplicated) progress-modal code.

**Tech Stack:** Laravel 12 (PHP), Inertia.js + React 19, Ant Design 6, PHPUnit feature tests, SQLite test DB (`tests/TestCase.php` enforced).

## Global Constraints

- All user-facing strings (flash messages, labels, progress-modal text) must be in Uzbek — no English UI text.
- Validation lives in a FormRequest (`app/Http/Requests/`), per this repo's layering convention.
- New route requires: `config/menu_permissions.php` already has the `employees-pull` key — no permission config changes needed (spec's chosen tradeoff: single route, `employees-pull` permission only — see spec's "Ruxsat o'zgarishi" section).
- Icons only from `@ant-design/icons`. Navigate with Inertia `router`/`Link`, never raw `fetch` for page navigation (the new JSON calls are for data, not navigation, so `fetch` is correct here — matches the existing `admin.sync.transfer-one` precedent).
- No new Laravel queue jobs — this plan deliberately does not touch `app/Jobs/Hikvision/`.
- Reference spec: `docs/superpowers/specs/2026-08-06-employee-pull-progress-design.md`.

---

## Task 1: Backend — new `pull-one` endpoint (additive, old endpoints untouched)

**Files:**
- Create: `app/Http/Requests/PullOneEmployeeRequest.php`
- Modify: `app/Http/Controllers/EmployeeController.php` (add `pullOne()` method; add `Log` and `JsonResponse` imports)
- Modify: `routes/web.php` (add one route)
- Modify: `resources/js/routes.js` (add one route builder)
- Test: `tests/Feature/Admin/EmployeePullOneTest.php`

**Interfaces:**
- Produces: `POST /admin/employees/pull-one` (name `admin.employees.pull.pull-one`), body `{ device_id: number }`, JSON response `{ status: 'success'|'partial'|'skipped'|'failed', device_id, device_name, device_ip, created, updated, linked, skipped, faces, failed, message }`. Later tasks (3, 5, 6) consume this exact shape.

- [ ] **Step 1: Write the failing test file**

Create `tests/Feature/Admin/EmployeePullOneTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Device;
use App\Services\Hikvision\EmployeePullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\Concerns\InteractsWithPermissions;
use Tests\TestCase;

class EmployeePullOneTest extends TestCase
{
    use InteractsWithPermissions;
    use RefreshDatabase;

    public function test_pull_one_imports_employees_and_returns_success_status(): void
    {
        Http::fake([
            '*UserInfo/Search*' => Http::response([
                'UserInfoSearch' => [
                    'responseStatusStrg' => 'OK',
                    'UserInfo' => [
                        [
                            'employeeNo' => '77001',
                            'name' => 'Pull One User',
                            'Valid' => [
                                'beginTime' => '2026-01-01T00:00:00',
                                'endTime' => '2030-12-31T23:59:59',
                            ],
                        ],
                    ],
                ],
            ], 200),
            '*FDSearch*' => Http::response('', 404),
            '*picture*' => Http::response('', 404),
        ]);

        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.employees.pull.pull-one'), [
                'device_id' => $device->id,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'device_id' => $device->id,
                'created' => 1,
                'failed' => 0,
            ]);

        $this->assertDatabaseHas('employees', ['card' => '77001', 'name' => 'Pull One User']);
        $this->assertTrue($device->employees()->where('card', '77001')->exists());
    }

    public function test_pull_one_skips_device_without_credentials(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
            'user' => null,
            'password' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.employees.pull.pull-one'), [
                'device_id' => $device->id,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'skipped',
                'message' => 'Login/parol sozlanmagan',
            ]);
    }

    public function test_pull_one_skips_offline_device(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => false,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.employees.pull.pull-one'), [
                'device_id' => $device->id,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'skipped',
                'message' => 'Qurilma ulanmagan',
            ]);
    }

    public function test_pull_one_returns_failed_status_when_service_throws(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $this->mock(EmployeePullService::class, function (MockInterface $mock) use ($device): void {
            $mock->shouldReceive('pullDevice')
                ->once()
                ->withArgs(fn (Device $argument): bool => $argument->id === $device->id)
                ->andThrow(new \RuntimeException('UserInfo Search javob bermadi: HTTP 401'));
        });

        $this->actingAs($admin)
            ->postJson(route('admin.employees.pull.pull-one'), [
                'device_id' => $device->id,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'message' => 'UserInfo Search javob bermadi: HTTP 401',
            ]);
    }

    public function test_pull_one_returns_partial_status_when_some_employees_fail(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $device = Device::factory()->create([
            'user_id' => $admin->id,
            'category_id' => Category::factory(),
            'is_online' => true,
        ]);

        $this->mock(EmployeePullService::class, function (MockInterface $mock) use ($device): void {
            $mock->shouldReceive('pullDevice')
                ->once()
                ->withArgs(fn (Device $argument): bool => $argument->id === $device->id)
                ->andReturn([
                    'created' => 1,
                    'updated' => 0,
                    'linked' => 0,
                    'skipped' => 0,
                    'failed' => 1,
                    'faces' => 0,
                ]);
        });

        $this->actingAs($admin)
            ->postJson(route('admin.employees.pull.pull-one'), [
                'device_id' => $device->id,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 'partial',
                'created' => 1,
                'failed' => 1,
            ]);
    }

    public function test_pull_one_requires_device_id(): void
    {
        $admin = $this->createUserWithPermissions([
            config('menu_permissions.resources')['employees-pull'],
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.employees.pull.pull-one'), [])
            ->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_pull_one_device(): void
    {
        $user = $this->createUserWithPermissions([
            config('menu_permissions.home'),
        ]);

        $device = Device::factory()->create([
            'category_id' => Category::factory(),
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.employees.pull.pull-one'), [
                'device_id' => $device->id,
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `composer test -- --filter=EmployeePullOneTest`
Expected: FAIL — route `admin.employees.pull.pull-one` not defined (`Symfony\Component\Routing\Exception\RouteNotFoundException` or similar), since neither the route nor the controller method exist yet.

- [ ] **Step 3: Create the FormRequest**

Create `app/Http/Requests/PullOneEmployeeRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PullOneEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['employees-pull']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', Rule::exists('devices', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_id.required' => 'Qurilma tanlanmagan.',
        ];
    }
}
```

- [ ] **Step 4: Add `pullOne()` to `EmployeeController`**

In `app/Http/Controllers/EmployeeController.php`, add these imports alongside the existing `use` block (after `use App\Http\Requests\PullEmployeesRequest;`):

```php
use App\Http\Requests\PullOneEmployeeRequest;
```

And add near the top of the `use` block (Laravel/Symfony response + logging, not yet imported in this file):

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
```

Then add this method to the class, directly after the existing `pullStore()` method (leave `pullStore()` itself untouched for now — it is removed in Task 2):

```php
    public function pullOne(PullOneEmployeeRequest $request): JsonResponse
    {
        $device = Device::query()->findOrFail($request->validated('device_id'));

        $base = [
            'device_id' => $device->id,
            'device_name' => (string) $device->name,
            'device_ip' => (string) $device->ip_address,
        ];

        $empty = ['created' => 0, 'updated' => 0, 'linked' => 0, 'skipped' => 0, 'faces' => 0, 'failed' => 0];

        if (! $device->hasIsapiCredentials()) {
            return response()->json([
                ...$base,
                ...$empty,
                'status' => 'skipped',
                'message' => 'Login/parol sozlanmagan',
            ]);
        }

        if (! $device->is_online) {
            return response()->json([
                ...$base,
                ...$empty,
                'status' => 'skipped',
                'message' => 'Qurilma ulanmagan',
            ]);
        }

        try {
            $result = $this->employeePullService->pullDevice($device);
        } catch (\Throwable $exception) {
            Log::error('Qurilmadan xodim tortish xatoligi', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                ...$base,
                ...$empty,
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ]);
        }

        $total = $result['created'] + $result['updated'] + $result['linked'] + $result['faces'];

        $status = match (true) {
            $result['failed'] > 0 && $total === 0 => 'failed',
            $result['failed'] > 0 => 'partial',
            default => 'success',
        };

        $message = sprintf(
            '%d yangi, %d yangilangan, %d bog‘langan, %d o‘tkazildi, %d yuz, %d xato.',
            $result['created'],
            $result['updated'],
            $result['linked'],
            $result['skipped'],
            $result['faces'],
            $result['failed'],
        );

        return response()->json([
            ...$base,
            ...$result,
            'status' => $status,
            'message' => $message,
        ]);
    }
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, immediately after the existing block:

```php
        Route::post('employees/pull', [EmployeeController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['employees-pull'])
            ->name('employees.pull.store');
```

insert:

```php
        Route::post('employees/pull-one', [EmployeeController::class, 'pullOne'])
            ->middleware('permission:'.$menuPermissions['employees-pull'])
            ->name('employees.pull.pull-one');
```

- [ ] **Step 6: Add the JS route builder**

In `resources/js/routes.js`, immediately after:

```js
    'admin.employees.pull.store': () => '/admin/employees/pull',
```

insert:

```js
    'admin.employees.pull.pull-one': () => '/admin/employees/pull-one',
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `composer test -- --filter=EmployeePullOneTest`
Expected: PASS (7 tests).

- [ ] **Step 8: Run the full suite and commit**

Run: `composer test`
Expected: PASS (no regressions — old `employees/pull` and `devices/{id}/pull-employees` endpoints are untouched by this task).

```bash
git add app/Http/Requests/PullOneEmployeeRequest.php app/Http/Controllers/EmployeeController.php routes/web.php resources/js/routes.js tests/Feature/Admin/EmployeePullOneTest.php
git commit -m "$(cat <<'EOF'
Add per-device pull-one endpoint for employee import

New POST /admin/employees/pull-one returns a structured per-device
result (status + message) instead of aggregate-only counters, laying
the groundwork for a progress-modal driven frontend.
EOF
)"
```

---

## Task 2: Backend — remove the old bulk/single-device pull endpoints

**Files:**
- Modify: `app/Http/Controllers/EmployeeController.php` (remove `pullStore()`)
- Modify: `app/Http/Controllers/DeviceActionController.php` (remove `pullEmployees()` + its now-unused constructor dependency)
- Modify: `app/Services/Hikvision/EmployeePullService.php` (remove `pullDevices()`)
- Delete: `app/Http/Requests/PullEmployeesRequest.php`
- Modify: `routes/web.php` (remove 2 routes)
- Modify: `resources/js/routes.js` (remove 2 route builders)
- Modify: `tests/Feature/Admin/EmployeePullPageTest.php` (remove the bulk-POST test)
- Modify: `tests/Feature/Admin/DeviceActionTest.php` (remove 2 pull tests)

**Interfaces:**
- Consumes: nothing new (this task only deletes code proven dead by Task 1's replacement).
- Produces: no new interface — confirms via the full test suite that nothing else referenced the removed code.

- [ ] **Step 1: Remove `pullStore()` from `EmployeeController`**

In `app/Http/Controllers/EmployeeController.php`, delete this entire method (it sits directly before the new `pullOne()` from Task 1):

```php
    public function pullStore(PullEmployeesRequest $request): RedirectResponse
    {
        $result = $this->employeePullService->pullDevices($request->validated('device_ids'));

        $message = sprintf(
            'Xodimlar tortildi: %d yangi, %d yangilangan, %d bog‘langan, %d o‘tkazildi, %d yuz, %d qurilma xato.',
            $result['created'],
            $result['updated'],
            $result['linked'],
            $result['skipped'],
            $result['faces'],
            $result['failed'],
        );

        if ($result['created'] + $result['updated'] + $result['linked'] + $result['faces'] === 0
            && $result['failed'] > 0) {
            return redirect()
                ->route('admin.employees.pull')
                ->with('error', $message.' Qurilma ulanishi yoki login/parolni tekshiring.');
        }

        if ($result['failed'] > 0) {
            return redirect()
                ->route('admin.employees.index')
                ->with('error', $message);
        }

        return redirect()
            ->route('admin.employees.index')
            ->with('success', $message);
    }
```

Also remove the now-unused import `use App\Http\Requests\PullEmployeesRequest;` from the top of the file (the `PullOneEmployeeRequest` import from Task 1 stays).

- [ ] **Step 2: Remove `pullEmployees()` from `DeviceActionController`**

In `app/Http/Controllers/DeviceActionController.php`:

Remove the import:
```php
use App\Services\Hikvision\EmployeePullService;
```

Change the constructor from:
```php
    public function __construct(
        private readonly HikvisionDeviceService $deviceService,
        private readonly EmployeeSyncService $employeeSyncService,
        private readonly EmployeePullService $employeePullService,
    ) {}
```
to:
```php
    public function __construct(
        private readonly HikvisionDeviceService $deviceService,
        private readonly EmployeeSyncService $employeeSyncService,
    ) {}
```

Delete the entire method:
```php
    public function pullEmployees(Device $device): RedirectResponse
    {
        if (! $device->hasIsapiCredentials()) {
            return back()->with('error', 'Qurilmada login/parol sozlanmagan.');
        }

        if (! $device->is_online) {
            return back()->with('error', 'Qurilma ulanmagan. Avval ulash yoki ping qiling, keyin xodimlarni torting.');
        }

        try {
            $result = $this->employeePullService->pullDevice($device);
        } catch (\Throwable $exception) {
            Log::error('Qurilmadan xodim tortish xatoligi', [
                'device_id' => $device->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Xodimlarni tortib bo‘lmadi: '.$exception->getMessage());
        }

        $message = sprintf(
            'Xodimlar tortildi: %d yangi, %d yangilangan, %d bog‘langan, %d o‘tkazildi, %d yuz, %d xato.',
            $result['created'],
            $result['updated'],
            $result['linked'],
            $result['skipped'],
            $result['faces'],
            $result['failed'],
        );

        if ($result['created'] + $result['updated'] + $result['linked'] === 0 && $result['failed'] > 0) {
            return back()->with('error', $message);
        }

        if ($result['failed'] > 0) {
            return back()->with('error', $message);
        }

        return back()->with('success', $message);
    }
```

- [ ] **Step 3: Remove `pullDevices()` from `EmployeePullService`**

In `app/Services/Hikvision/EmployeePullService.php`, delete the entire method (it sits between `pullAllDevices()` and `pullDevice()`):

```php
    /**
     * @param  list<int>  $deviceIds
     * @return array{created:int, updated:int, linked:int, skipped:int, failed:int, faces:int, devices:int}
     */
    public function pullDevices(array $deviceIds): array
    {
        $created = 0;
        $updated = 0;
        $linked = 0;
        $skipped = 0;
        $failed = 0;
        $faces = 0;
        $devices = 0;

        Device::query()
            ->whereIn('id', $deviceIds)
            ->orderBy('id')
            ->each(function (Device $device) use (&$created, &$updated, &$linked, &$skipped, &$failed, &$faces, &$devices): void {
                if (! $device->hasIsapiCredentials()) {
                    $failed++;

                    return;
                }

                $devices++;

                try {
                    $result = $this->pullDevice($device);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $linked += $result['linked'];
                    $skipped += $result['skipped'];
                    $faces += $result['faces'];
                } catch (\Throwable $exception) {
                    $failed++;
                    Log::warning('Hikvision: tanlangan qurilmadan xodim tortishda xatolik', [
                        'device_id' => $device->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        Log::info('Hikvision: qo‘lda xodimlar tortib olindi', compact(
            'devices',
            'created',
            'updated',
            'linked',
            'skipped',
            'failed',
            'faces',
        ));

        return compact('created', 'updated', 'linked', 'skipped', 'failed', 'faces', 'devices');
    }
```

- [ ] **Step 4: Delete the old FormRequest**

Delete `app/Http/Requests/PullEmployeesRequest.php` entirely (its `device_ids`-array validation is no longer used anywhere — `PullOneEmployeeRequest` from Task 1 fully replaces it).

- [ ] **Step 5: Remove the old routes**

In `routes/web.php`, delete:

```php
        Route::post('devices/{device}/pull-employees', [DeviceActionController::class, 'pullEmployees'])
            ->middleware('permission:'.$menuPermissions['devices'])
            ->name('devices.pull-employees');
```

and:

```php
        Route::post('employees/pull', [EmployeeController::class, 'pullStore'])
            ->middleware('permission:'.$menuPermissions['employees-pull'])
            ->name('employees.pull.store');
```

(The `GET employees/pull` route — `employees.pull`, page render — stays untouched.)

- [ ] **Step 6: Remove the old JS route builders**

In `resources/js/routes.js`, delete:

```js
    'admin.devices.pull-employees': (id) => `/admin/devices/${id}/pull-employees`,
```

and:

```js
    'admin.employees.pull.store': () => '/admin/employees/pull',
```

- [ ] **Step 7: Remove the now-obsolete tests**

In `tests/Feature/Admin/EmployeePullPageTest.php`, delete the `test_pull_store_imports_employees_without_duplicates` method (it posts to the now-removed `admin.employees.pull.store` route) and the now-unused imports it required if no longer used elsewhere in the file (`Category`, `User`, `Http`, `ValidateCsrfToken`, `Employee` — check each is still used by the remaining two tests; `Employee` and `Http`/`Category`/`User`/`ValidateCsrfToken` are only used by the deleted test, so remove those `use` statements too, keeping `Device`, `Assert`, `InteractsWithPermissions`, `RefreshDatabase`, `TestCase`).

In `tests/Feature/Admin/DeviceActionTest.php`, delete both `test_authorized_user_can_pull_employees_from_device` and `test_pull_employees_rejects_offline_device` (they post to the now-removed `admin.devices.pull-employees` route). Check whether `Http` and `Employee` (via `use App\Models\Employee;`) are still used elsewhere in the file (`Employee` is used by `test_clear_removes_employees_immediately_without_queue` and `test_remove_employees_runs_immediately_without_queue`, and `Http` is used by several remaining tests) — keep those imports.

- [ ] **Step 8: Run the full suite**

Run: `composer test`
Expected: PASS — no test references the removed routes/methods anymore, and `EmployeePullOneTest` (Task 1) plus the untouched `EmployeePullServiceTest` still pass.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/EmployeeController.php app/Http/Controllers/DeviceActionController.php app/Services/Hikvision/EmployeePullService.php routes/web.php resources/js/routes.js tests/Feature/Admin/EmployeePullPageTest.php tests/Feature/Admin/DeviceActionTest.php
git rm app/Http/Requests/PullEmployeesRequest.php
git commit -m "$(cat <<'EOF'
Remove superseded bulk and single-device pull endpoints

pull-one (added previously) fully replaces employees/pull (POST) and
devices/{id}/pull-employees; both were synchronous, unbounded HTTP
calls with only aggregate error counters.
EOF
)"
```

---

## Task 3: Frontend — shared `ProgressModal`, `xsrf`, and `employeePull` utilities

**Files:**
- Create: `resources/js/utils/xsrf.js`
- Create: `resources/js/Components/ProgressModal.jsx`
- Create: `resources/js/utils/employeePull.js`

**Interfaces:**
- Consumes: `admin.employees.pull.pull-one` route builder (Task 1).
- Produces:
  - `getXsrfToken(): string` from `utils/xsrf.js`
  - `<ProgressModal open, title, current, total, logs, done, cancelled, onClose, onCancel />` from `Components/ProgressModal.jsx`
  - `pullOneDevice(deviceId: number): Promise<{status, device_id, device_name, device_ip, created, updated, linked, skipped, faces, failed, message}>` and `formatPullLogLine(result): string` from `utils/employeePull.js`. Tasks 4, 5, 6 import these.

- [ ] **Step 1: Create the XSRF helper**

Create `resources/js/utils/xsrf.js`:

```js
export function getXsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
```

- [ ] **Step 2: Create the shared `ProgressModal` component**

Create `resources/js/Components/ProgressModal.jsx` (extracted from `Sync/Index.jsx`'s `SyncProgressModal`, generalized with a `title` prop):

```jsx
import { Button, Modal, Progress } from 'antd';

export default function ProgressModal({
    open,
    title,
    current,
    total,
    logs,
    done,
    cancelled,
    onClose,
    onCancel,
}) {
    const percent = total > 0 ? Math.round((current / total) * 100) : 0;
    const logText = logs.join('\n');

    return (
        <Modal
            open={open}
            title={title}
            closable={done}
            maskClosable={done}
            keyboard={done}
            onCancel={onClose}
            footer={done ? (
                <Button type="primary" onClick={onClose}>
                    Yopish
                </Button>
            ) : (
                <Button danger onClick={onCancel}>
                    Bekor qilish
                </Button>
            )}
            width={640}
            destroyOnHidden
        >
            <div className="space-y-4">
                <div>
                    <p className="mb-2 text-sm font-semibold text-ink">
                        Progress
                        {' '}
                        {current}
                        /
                        {total}
                        {' '}
                        (
                        {percent}
                        %)
                        {cancelled && !done ? ' — to‘xtatilmoqda...' : null}
                        {done && cancelled ? ' — bekor qilindi' : null}
                        {done && !cancelled ? ' — yakunlandi' : null}
                    </p>
                    <Progress
                        percent={percent}
                        status={done ? (cancelled || logs.length > 0 ? 'exception' : 'success') : 'active'}
                    />
                </div>

                <div>
                    <p className="mb-2 text-sm font-semibold text-ink">
                        Xatolar / jurnal
                        {logs.length > 0 ? ` (${logs.length})` : ''}
                    </p>
                    <textarea
                        readOnly
                        value={logText || (done ? 'Xato yo‘q.' : 'Jarayon ketmoqda...')}
                        className="h-52 w-full resize-y rounded-lg border border-line bg-surface-muted px-3 py-2 font-mono text-xs leading-5 text-ink"
                    />
                </div>
            </div>
        </Modal>
    );
}
```

- [ ] **Step 3: Create the `employeePull` fetch + formatting helper**

Create `resources/js/utils/employeePull.js`:

```js
import { getXsrfToken } from './xsrf';
import { route } from '../routes';

/**
 * @param {number} deviceId
 * @returns {Promise<{status: string, device_id: number, device_name: string, device_ip: string, created: number, updated: number, linked: number, skipped: number, faces: number, failed: number, message: string}>}
 */
export async function pullOneDevice(deviceId) {
    const empty = { created: 0, updated: 0, linked: 0, skipped: 0, faces: 0, failed: 0 };

    try {
        const response = await fetch(route('admin.employees.pull.pull-one'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ device_id: deviceId }),
        });

        const result = await response.json().catch(() => null);

        if (!response.ok || !result) {
            return {
                ...empty,
                device_id: deviceId,
                device_name: '',
                device_ip: '',
                status: 'failed',
                message: `HTTP ${response.status}`,
            };
        }

        return result;
    } catch (error) {
        return {
            ...empty,
            device_id: deviceId,
            device_name: '',
            device_ip: '',
            status: 'failed',
            message: error.message || 'Tarmoq xatosi',
        };
    }
}

/**
 * @param {{device_ip: string, device_name: string, status: string, message: string}} result
 * @returns {string}
 */
export function formatPullLogLine(result) {
    const label = `IP: ${result.device_ip || '—'} (${result.device_name || '—'})`;

    if (result.status === 'success' || result.status === 'partial') {
        return `${label}: ${result.message}`;
    }

    return `${label} — Xato: ${result.message}`;
}
```

- [ ] **Step 4: Verify the build**

Run: `npm run build`
Expected: succeeds with no errors (new files have no syntax issues and no dangling imports yet, since nothing imports them until Tasks 4–6).

- [ ] **Step 5: Commit**

```bash
git add resources/js/utils/xsrf.js resources/js/Components/ProgressModal.jsx resources/js/utils/employeePull.js
git commit -m "$(cat <<'EOF'
Add shared ProgressModal, xsrf, and employeePull frontend utilities

Extracts the progress-modal pattern already proven on the Sync page
so the employee pull flows (Tasks 4-6) can reuse it instead of each
page re-implementing its own fetch/XSRF/modal boilerplate.
EOF
)"
```

---

## Task 4: Refactor `Sync/Index.jsx` onto the shared `ProgressModal` + `xsrf` util

**Files:**
- Modify: `resources/js/Pages/Admin/Sync/Index.jsx`

**Interfaces:**
- Consumes: `ProgressModal` and `getXsrfToken` from Task 3.
- Produces: no new interface — pure refactor, page behavior is identical.

- [ ] **Step 1: Remove the local `getXsrfToken` and import the shared one**

In `resources/js/Pages/Admin/Sync/Index.jsx`, delete the local function:

```js
function getXsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
```

Add to the imports at the top of the file:

```js
import ProgressModal from '../../../Components/ProgressModal';
import { getXsrfToken } from '../../../utils/xsrf';
```

- [ ] **Step 2: Remove the local `SyncProgressModal` component**

Delete the entire `function SyncProgressModal({ ... }) { ... }` block (currently lines 180–249).

- [ ] **Step 3: Point the JSX usage at the shared component with a title**

Change:

```jsx
            <SyncProgressModal
                open={progressOpen}
                current={progressCurrent}
                total={progressTotal}
                logs={progressLogs}
                done={progressDone}
                cancelled={progressCancelled}
                onCancel={() => {
                    abortRef.current = true;
                    setProgressCancelled(true);
                }}
                onClose={() => setProgressOpen(false)}
            />
```

to:

```jsx
            <ProgressModal
                title="Sinxronizatsiya jarayoni"
                open={progressOpen}
                current={progressCurrent}
                total={progressTotal}
                logs={progressLogs}
                done={progressDone}
                cancelled={progressCancelled}
                onCancel={() => {
                    abortRef.current = true;
                    setProgressCancelled(true);
                }}
                onClose={() => setProgressOpen(false)}
            />
```

- [ ] **Step 4: Verify the build**

Run: `npm run build`
Expected: succeeds with no errors.

- [ ] **Step 5: Manual browser check**

Run `composer dev`, log in as an admin with the `Sinxronizatsiya` permission, open `Admin → Sinxronizatsiya`, select at least one device and one employee, click "Yuborish", confirm the progress modal still opens, updates, and closes exactly as before (title now reads "Sinxronizatsiya jarayoni" from the shared component).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Admin/Sync/Index.jsx
git commit -m "$(cat <<'EOF'
Refactor Sync page onto the shared ProgressModal and xsrf util

No behavior change - deduplicates code now shared with the employee
pull pages.
EOF
)"
```

---

## Task 5: `Pull.jsx` — per-device progress loop

**Files:**
- Modify: `resources/js/Pages/Admin/Employees/Pull.jsx` (full rewrite of the file's logic; markup structure mostly preserved)

**Interfaces:**
- Consumes: `pullOneDevice`, `formatPullLogLine` (Task 3), `ProgressModal` (Task 3), `admin.employees.pull.pull-one` (Task 1).
- Produces: no new interface — this is a leaf page.

- [ ] **Step 1: Replace the full file content**

Replace the entire contents of `resources/js/Pages/Admin/Employees/Pull.jsx` with:

```jsx
import { Link } from '@inertiajs/react';
import { App as AntApp, Button, Checkbox, Input, Select } from 'antd';
import { useMemo, useRef, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader from '../../../Components/PageHeader';
import StatusTag from '../../../Components/StatusTag';
import ProgressModal from '../../../Components/ProgressModal';
import { pullOneDevice, formatPullLogLine } from '../../../utils/employeePull';

export default function Pull({ devices = [], categories = [] }) {
    const { modal, message } = AntApp.useApp();
    const [selectedIds, setSelectedIds] = useState([]);
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState(null);
    const [pulling, setPulling] = useState(false);
    const [progressOpen, setProgressOpen] = useState(false);
    const [progressCurrent, setProgressCurrent] = useState(0);
    const [progressTotal, setProgressTotal] = useState(0);
    const [progressLogs, setProgressLogs] = useState([]);
    const [progressDone, setProgressDone] = useState(false);
    const [progressCancelled, setProgressCancelled] = useState(false);
    const abortRef = useRef(false);

    const filteredDevices = useMemo(() => {
        let rows = devices;

        if (categoryFilter) {
            rows = rows.filter((device) => device.category?.id === categoryFilter);
        }

        const query = search.trim().toLowerCase();

        if (!query) {
            return rows;
        }

        return rows.filter((device) => [
            device.name,
            device.ip_address,
            device.type_label,
            device.category?.name,
        ].some((value) => String(value || '').toLowerCase().includes(query)));
    }, [devices, categoryFilter, search]);

    const selectedDevices = useMemo(
        () => devices.filter((device) => selectedIds.includes(device.id)),
        [devices, selectedIds],
    );
    const offlineSelectedCount = selectedDevices.filter((device) => !device.is_online).length;

    const allFilteredSelected = filteredDevices.length > 0
        && filteredDevices.every((device) => selectedIds.includes(device.id));
    const someFilteredSelected = filteredDevices.some((device) => selectedIds.includes(device.id))
        && !allFilteredSelected;

    const toggleOne = (id, checked) => {
        setSelectedIds((prev) => (checked ? [...prev, id] : prev.filter((item) => item !== id)));
    };

    const toggleAll = (checked) => {
        const ids = filteredDevices
            .filter((device) => device.has_credentials)
            .map((device) => device.id);

        setSelectedIds((prev) => {
            if (!checked) {
                return prev.filter((id) => !ids.includes(id));
            }

            return [...new Set([...prev, ...ids])];
        });
    };

    const runPullJobs = async () => {
        abortRef.current = false;
        setPulling(true);
        setProgressOpen(true);
        setProgressCurrent(0);
        setProgressTotal(selectedIds.length);
        setProgressLogs([]);
        setProgressDone(false);
        setProgressCancelled(false);

        let success = 0;
        let failed = 0;

        for (let i = 0; i < selectedIds.length; i += 1) {
            if (abortRef.current) {
                setProgressCancelled(true);
                break;
            }

            const result = await pullOneDevice(selectedIds[i]);

            if (result.status === 'success' || result.status === 'partial') {
                success += 1;
            } else {
                failed += 1;
            }

            setProgressLogs((prev) => [...prev, formatPullLogLine(result)]);
            setProgressCurrent(i + 1);
        }

        setProgressDone(true);
        setPulling(false);
        setSelectedIds([]);

        if (abortRef.current) {
            message.warning(`Bekor qilindi: ${success} qurilma yakunlandi, ${failed} xato.`);
        } else if (failed === 0) {
            message.success(`Xodim tortish yakunlandi: ${success} qurilma muvaffaqiyatli.`);
        } else {
            message.error(`Xodim tortish yakunlandi: ${success} muvaffaqiyatli, ${failed} xato.`);
        }
    };

    const submitPull = () => {
        if (selectedIds.length === 0) {
            return;
        }

        const start = () => {
            runPullJobs();
        };

        if (offlineSelectedCount > 0) {
            modal.confirm({
                title: 'Ulanmagan qurilmalar bor',
                content: `${offlineSelectedCount} ta tanlangan qurilma hozir ulanmagan. Ular o'tkazib yuboriladi. Davom etasizmi?`,
                okText: 'Ha, tortish',
                cancelText: 'Bekor qilish',
                onOk: () => new Promise((resolve) => {
                    start();
                    resolve();
                }),
            });
            return;
        }

        start();
    };

    const canSubmit = selectedIds.length > 0 && !pulling;

    return (
        <AdminLayout title="Xodim tortish">
            <PageHeader
                title="Xodim tortish"
                subtitle="Tanlangan qurilmalardan xodimlar va yuz rasmlarini tortib oladi. Bir xil karta (employeeNo) dublikat yaratilmaydi."
                actions={(
                    <Link
                        href={route('admin.employees.index')}
                        className="inline-flex h-10 items-center rounded-lg border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-muted"
                    >
                        Xodimlarga qaytish
                    </Link>
                )}
            />

            <ProgressModal
                title="Xodim tortish jarayoni"
                open={progressOpen}
                current={progressCurrent}
                total={progressTotal}
                logs={progressLogs}
                done={progressDone}
                cancelled={progressCancelled}
                onCancel={() => {
                    abortRef.current = true;
                    setProgressCancelled(true);
                }}
                onClose={() => setProgressOpen(false)}
            />

            <div className="mb-5 rounded-xl border border-line bg-surface p-4 shadow-ambient">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <Select
                        allowClear
                        placeholder="Kategoriya"
                        className="w-full"
                        value={categoryFilter}
                        options={categories.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={setCategoryFilter}
                    />
                    <Input
                        allowClear
                        placeholder="Qidiruv..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button
                        type="primary"
                        className="!font-bold"
                        loading={pulling}
                        disabled={!canSubmit}
                        onClick={submitPull}
                    >
                        {pulling
                            ? 'Tortib olinmoqda...'
                            : `Tortib olish${selectedIds.length ? ` (${selectedIds.length})` : ''}`}
                    </Button>
                </div>
                {offlineSelectedCount > 0 ? (
                    <p className="mt-3 text-sm font-medium text-amber-600">
                        {offlineSelectedCount} ta tanlangan qurilma hozir ulanmagan — tortish vaqtida o'tkazib yuboriladi.
                    </p>
                ) : null}
            </div>

            <div className="rounded-xl border border-line bg-surface shadow-ambient">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3">
                    <div>
                        <h2 className="text-base font-extrabold text-ink">Qurilmalar</h2>
                        <p className="mt-0.5 text-sm text-ink-muted">
                            Tanlangan:
                            {' '}
                            <span className="font-semibold text-ink">{selectedIds.length}</span>
                        </p>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="data-table devices-table min-w-full">
                        <thead>
                            <tr>
                                <th className="devices-table__checkbox">
                                    <Checkbox
                                        checked={allFilteredSelected}
                                        indeterminate={someFilteredSelected}
                                        onChange={(e) => toggleAll(e.target.checked)}
                                    />
                                </th>
                                <th className="w-12">№</th>
                                <th>Holat</th>
                                <th>IP</th>
                                <th>Nomi</th>
                                <th>Tur</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredDevices.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-10 text-center text-sm text-ink-muted">
                                        Qurilmalar topilmadi
                                    </td>
                                </tr>
                            ) : filteredDevices.map((device, index) => (
                                <tr
                                    key={device.id}
                                    className={selectedIds.includes(device.id) ? 'bg-brand-50/60' : undefined}
                                >
                                    <td className="devices-table__checkbox">
                                        <Checkbox
                                            checked={selectedIds.includes(device.id)}
                                            disabled={!device.has_credentials}
                                            onChange={(e) => toggleOne(device.id, e.target.checked)}
                                        />
                                    </td>
                                    <td className="font-medium text-ink-subtle">{index + 1}</td>
                                    <td>
                                        <StatusTag tone={device.is_online ? 'success' : 'neutral'}>
                                            {device.is_online ? 'Ulangan' : 'Ulanmagan'}
                                        </StatusTag>
                                    </td>
                                    <td className="font-medium">{device.ip_address}</td>
                                    <td className="font-medium">
                                        <div>{device.name}</div>
                                        {device.category?.name ? (
                                            <div className="text-xs font-medium text-ink-muted">
                                                {device.category.name}
                                            </div>
                                        ) : null}
                                    </td>
                                    <td className="font-medium">{device.type_label || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
```

- [ ] **Step 2: Verify the build**

Run: `npm run build`
Expected: succeeds with no errors, no unused-import warnings.

- [ ] **Step 3: Manual browser check**

Run `composer dev`. Set up at least two devices in Admin → Qurilmalar with real or fake ISAPI credentials — one online, one offline (to exercise the confirm-dialog warning) — then:
- Open `Admin → Xodimlar → Xodim tortish`.
- Select both devices, click "Tortib olish". Confirm the "Ulanmagan qurilmalar bor" dialog appears, confirm through it.
- Confirm the progress modal opens, advances through both devices, and shows one log line per device (success or `— Xato: ...`).
- Confirm clicking "Bekor qilish" mid-run stops further devices and marks the run cancelled.
- Confirm "Yopish" closes the modal and the device selection is cleared.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Employees/Pull.jsx
git commit -m "$(cat <<'EOF'
Drive bulk employee pull through per-device progress modal

Replaces the single all-devices-in-one-request POST (timeout-prone,
aggregate-only errors) with a sequential per-device loop against
pull-one, showing each device's specific result live.
EOF
)"
```

---

## Task 6: `Devices/Show.jsx` — unify the single-device pull button

**Files:**
- Modify: `resources/js/Pages/Admin/Devices/Show.jsx`

**Interfaces:**
- Consumes: `pullOneDevice`, `formatPullLogLine`, `ProgressModal` (Task 3).
- Produces: no new interface — leaf page.

- [ ] **Step 1: Add imports**

At the top of `resources/js/Pages/Admin/Devices/Show.jsx`, add:

```js
import ProgressModal from '../../../Components/ProgressModal';
import { pullOneDevice, formatPullLogLine } from '../../../utils/employeePull';
```

- [ ] **Step 2: Remove `pull` from the shared `runAction()` routing table and its confirm branch**

In the `routes` object inside `runAction()`, delete:

```js
        pull: route('admin.devices.pull-employees', deviceId),
```

Delete the entire `if (action === 'pull') { ... }` block:

```js
    if (action === 'pull') {
        modal.confirm({
            title: 'Xodimlarni tortish',
            content: 'Qurilmadagi xodimlar bazaga yuklanadi. Bir xil karta (employeeNo) dublikat yaratilmaydi. Davom etasizmi?',
            okText: 'Ha, tortish',
            cancelText: 'Bekor qilish',
            onOk: () => new Promise((resolve, reject) => {
                setBusy(action);
                router.post(url, {}, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                    onFinish: () => setBusy(null),
                });
            }),
        });
        return;
    }
```

- [ ] **Step 3: Add pull-specific state and handler to `DeviceQuickActions`**

Change:

```jsx
function DeviceQuickActions({ device }) {
    const { modal } = AntApp.useApp();
    const [busy, setBusy] = useState(null);

    const run = (action) => runAction(device.id, action, { modal, setBusy });
```

to:

```jsx
function DeviceQuickActions({ device }) {
    const { modal, message } = AntApp.useApp();
    const [busy, setBusy] = useState(null);
    const [pullModalOpen, setPullModalOpen] = useState(false);
    const [pullLogs, setPullLogs] = useState([]);
    const [pullDone, setPullDone] = useState(false);

    const run = (action) => runAction(device.id, action, { modal, setBusy });

    const runPull = () => {
        modal.confirm({
            title: 'Xodimlarni tortish',
            content: 'Qurilmadagi xodimlar bazaga yuklanadi. Bir xil karta (employeeNo) dublikat yaratilmaydi. Davom etasizmi?',
            okText: 'Ha, tortish',
            cancelText: 'Bekor qilish',
            onOk: async () => {
                setBusy('pull');
                setPullLogs([]);
                setPullDone(false);
                setPullModalOpen(true);

                const result = await pullOneDevice(device.id);

                setPullLogs([formatPullLogLine(result)]);
                setPullDone(true);
                setBusy(null);

                if (result.status === 'success' || result.status === 'partial') {
                    message.success('Xodim tortish yakunlandi.');
                } else {
                    message.error('Xodim tortish yakunlandi: xato bor.');
                }

                router.reload({ only: ['device'] });
            },
        });
    };
```

- [ ] **Step 4: Point the button at `runPull` and add the modal**

Change:

```jsx
            <Button
                icon={<CloudDownloadOutlined />}
                loading={busy === 'pull'}
                disabled={Boolean(busy) && busy !== 'pull'}
                onClick={() => run('pull')}
            >
                Xodimlarni tortish
            </Button>
```

to:

```jsx
            <Button
                icon={<CloudDownloadOutlined />}
                loading={busy === 'pull'}
                disabled={Boolean(busy) && busy !== 'pull'}
                onClick={runPull}
            >
                Xodimlarni tortish
            </Button>
```

Then add the modal at the end of the `DeviceQuickActions` return block, as a sibling of the closing `</div>` wrapping the buttons (i.e. after the last `<Button>` in the list, still inside the same top-level `<div className="flex flex-wrap gap-2">...</div>` fragment — place it as the final child before that div's closing tag):

```jsx
            <ProgressModal
                title="Xodim tortish jarayoni"
                open={pullModalOpen}
                current={pullDone ? 1 : 0}
                total={1}
                logs={pullLogs}
                done={pullDone}
                cancelled={false}
                onClose={() => setPullModalOpen(false)}
                onCancel={() => setPullModalOpen(false)}
            />
```

- [ ] **Step 5: Verify the build**

Run: `npm run build`
Expected: succeeds with no errors.

- [ ] **Step 6: Manual browser check**

Run `composer dev`. Open a device's show page (`Admin → Qurilmalar → {device}`) for an online device with credentials, click "Xodimlarni tortish", confirm the dialog, confirm the progress modal opens with `1/1`, shows the one result line, and the device's employee list (`DeviceEmployeesPanel`) refreshes after closing. Repeat for an offline device and confirm the modal shows the "Qurilma ulanmagan" log line instead of a generic flash message.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Admin/Devices/Show.jsx
git commit -m "$(cat <<'EOF'
Unify device-page pull button onto the shared pull-one flow

Replaces the Inertia redirect+flash result with the same progress
modal used on the bulk pull page, surfacing the exact per-device
error message instead of a generic banner.
EOF
)"
```

---

## Task 7: Final verification

**Files:** none (verification only).

- [ ] **Step 1: Full backend test suite**

Run: `composer test`
Expected: all green.

- [ ] **Step 2: Code style**

Run: `vendor/bin/pint`
Expected: no changes needed, or auto-fixes applied; if it modifies files, re-run `composer test` and commit the formatting fix separately (`git add -u && git commit -m "Apply Pint formatting"`).

- [ ] **Step 3: Frontend build**

Run: `npm run build`
Expected: succeeds, no warnings about unused imports or unresolved modules.

- [ ] **Step 4: Full manual walkthrough**

With `composer dev` running:
1. `Admin → Xodimlar → Xodim tortish` — bulk pull across 2+ devices (mix of online/offline/no-credentials), confirm per-device log lines are accurate and specific.
2. `Admin → Qurilmalar → {device}` — single-device pull, confirm same clarity.
3. `Admin → Sinxronizatsiya` — confirm unaffected (Task 4 refactor only).
4. Confirm no browser console errors on any of the three pages.

- [ ] **Step 5: Confirm spec coverage**

Re-read `docs/superpowers/specs/2026-08-06-employee-pull-progress-design.md` and confirm every section (Backend, Frontend, Testing, Permission note) is reflected in the merged changes. No further action needed if so — this plan is complete.
