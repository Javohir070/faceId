# Employee Edit Card Lock Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Edit sahifada ulangan qurilmalarni ko‘rsatish/o‘chirish; karta raqamini faqat barcha qurilmalardan uzilgach o‘zgartirishga ruxsat berish (UI + backend).

**Architecture:** Show dagi `EmployeeDevicesPanel` ni umumiy komponentga chiqarib Form da qayta ishlatish; edit resource ga `devices` qo‘shish; `UpdateEmployeeRequest` da bog‘langan holda `card` o‘zgarishini taqiqlash; `removeDevices` `back()` ga redirect.

**Tech Stack:** Laravel 12, Inertia React, Ant Design, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-03-employee-edit-card-lock-design.md`

## Global Constraints

- Karta faqat `devices` bog‘lanishi **0** bo‘lganda o‘zgaradi.
- Create form o‘zgarmaydi.
- Remove job / detach vaqtini o‘zgartirmaslik — mavjud `queueRemoveFromDevices` qoladi.
- Devices panel format: `id`, `name`, `ip_address`, `is_synced`, `synced_at` (Show bilan bir xil).
- Commit faqat foydalanuvchi so‘raganda (bu repoda default).

## File map

| File | Role |
|------|------|
| `app/Services/EmployeeService.php` | `loadForEdit` ga `devices` |
| `app/Http/Resources/EmployeeResource.php` | `formFields()` ga `devices` |
| `app/Http/Requests/UpdateEmployeeRequest.php` | card lock validation |
| `app/Http/Controllers/EmployeeController.php` | `removeDevices` → `back()` |
| `resources/js/Components/EmployeeDevicesPanel.jsx` | umumiy panel (Create) |
| `resources/js/Pages/Admin/Employees/Show.jsx` | panel import |
| `resources/js/Pages/Admin/Employees/Form.jsx` | panel + card disabled |
| `tests/Feature/Admin/EmployeeAdminTest.php` | yangi testlar |

---

### Task 1: Backend — edit devices + card validation + redirect back

**Files:**
- Modify: `app/Services/EmployeeService.php`
- Modify: `app/Http/Resources/EmployeeResource.php`
- Modify: `app/Http/Requests/UpdateEmployeeRequest.php`
- Modify: `app/Http/Controllers/EmployeeController.php`
- Test: `tests/Feature/Admin/EmployeeAdminTest.php`

**Interfaces:**
- Consumes: `Employee::devices()`, mavjud Show devices map format
- Produces: edit Inertia `employee.devices[]`; card change rejected when linked

- [ ] **Step 1: Write failing tests**

`tests/Feature/Admin/EmployeeAdminTest.php` ga qo‘shing:

```php
public function test_employee_edit_includes_linked_devices(): void
{
    $admin = $this->createUserWithPermissions([
        config('menu_permissions.resources')['employees'],
    ]);

    $device = Device::factory()->create([
        'user_id' => $admin->id,
        'category_id' => Category::factory(),
    ]);
    $employee = Employee::factory()->create();
    $employee->devices()->attach($device->id, ['synced_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.employees.edit', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Employees/Form')
            ->has('employee.devices', 1)
            ->where('employee.devices.0.id', $device->id)
            ->where('employee.devices.0.name', $device->name));
}

public function test_cannot_change_card_while_linked_to_devices(): void
{
    $admin = $this->createUserWithPermissions([
        config('menu_permissions.resources')['employees'],
    ]);

    $device = Device::factory()->create([
        'user_id' => $admin->id,
        'category_id' => Category::factory(),
    ]);
    $employee = Employee::factory()->create([
        'card' => '11111111',
        'department_id' => Department::factory(),
        'schedule_id' => Schedule::factory(),
    ]);
    $employee->devices()->attach($device->id, ['synced_at' => now()]);

    $this->actingAs($admin)
        ->put(route('admin.employees.update', $employee), [
            'name' => $employee->name,
            'card' => '22222222',
            'gender' => $employee->gender,
            'position' => $employee->position,
            'department_id' => $employee->department_id,
            'schedule_id' => $employee->schedule_id,
            'valid_from_dt' => $employee->valid_from_dt->format('Y-m-d\TH:i'),
            'valid_to_dt' => $employee->valid_to_dt->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('card');

    $this->assertSame('11111111', $employee->fresh()->card);
}

public function test_can_change_card_when_not_linked_to_devices(): void
{
    $admin = $this->createUserWithPermissions([
        config('menu_permissions.resources')['employees'],
    ]);

    $employee = Employee::factory()->create([
        'card' => '11111111',
        'department_id' => Department::factory(),
        'schedule_id' => Schedule::factory(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.employees.update', $employee), [
            'name' => $employee->name,
            'card' => '22222222',
            'gender' => $employee->gender,
            'position' => $employee->position,
            'department_id' => $employee->department_id,
            'schedule_id' => $employee->schedule_id,
            'valid_from_dt' => $employee->valid_from_dt->format('Y-m-d\TH:i'),
            'valid_to_dt' => $employee->valid_to_dt->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect(route('admin.employees.index'));

    $this->assertSame('22222222', $employee->fresh()->card);
}

public function test_remove_devices_from_edit_redirects_back_to_edit(): void
{
    Http::fake(['*' => Http::response('', 200)]);

    $admin = $this->createUserWithPermissions([
        config('menu_permissions.resources')['employees'],
    ]);

    $device = Device::factory()->create([
        'user_id' => $admin->id,
        'category_id' => Category::factory(),
    ]);
    $employee = Employee::factory()->create();
    $employee->devices()->attach($device->id, ['synced_at' => now()]);

    $this->actingAs($admin)
        ->from(route('admin.employees.edit', $employee))
        ->post(route('admin.employees.remove-devices', $employee), [
            'device_ids' => [$device->id],
        ])
        ->assertRedirect(route('admin.employees.edit', $employee))
        ->assertSessionHas('success');
}
```

Mavjud `test_employee_show_lists_devices_and_can_remove_from_device` da redirect `show` ga assert qilingan — `back()` dan keyin Show `from` yo‘q bo‘lsa default fallback kerak. `removeDevices` ni shunday qiling:

```php
return redirect()
    ->back(fallback: route('admin.employees.show', $employee))
    ->with('success', 'Xodimni qurilmalardan o‘chirish navbatga qo‘yildi.');
```

Shunda Show test (to‘g‘ridan-to‘g‘ri POST, from yo‘q) show ga qaytadi; Edit `from` bilan editga qaytadi.

Agar Show testi `from`siz `back()` da old URL yo‘qligi sabab boshqa joyga ketsa, Show testni ham `->from(route('admin.employees.show', $employee))` bilan yangilang.

- [ ] **Step 2: Run tests — expect fail**

```bash
php artisan test --filter="test_employee_edit_includes_linked_devices|test_cannot_change_card_while_linked_to_devices|test_can_change_card_when_not_linked_to_devices|test_remove_devices_from_edit_redirects_back_to_edit"
```

Expected: FAIL (devices yo‘q / card o‘zgaradi / redirect show).

- [ ] **Step 3: Implement backend**

`EmployeeService::loadForEdit`:

```php
return $this->employeeRepository->loadRelations($employee, ['department', 'schedule', 'face', 'devices']);
```

`EmployeeResource::formFields()` oxiriga (Show dagi map bilan bir xil):

```php
'devices' => $this->devices
    ->sortBy('name')
    ->values()
    ->map(fn ($device): array => [
        'id' => $device->id,
        'name' => $device->name,
        'ip_address' => $device->ip_address,
        'is_synced' => filled($device->pivot->synced_at),
        'synced_at' => filled($device->pivot->synced_at)
            ? Carbon::parse($device->pivot->synced_at)->format('Y-m-d H:i')
            : null,
    ]),
```

`UpdateEmployeeRequest::rules()` ichida `card`:

```php
'card' => [
    'required',
    'digits_between:1,14',
    Rule::unique('employees', 'card')->ignore($employee->id),
    function (string $attribute, mixed $value, \Closure $fail) use ($employee): void {
        if ((string) $value === (string) $employee->card) {
            return;
        }

        if ($employee->devices()->exists()) {
            $fail('Karta raqamini o‘zgartirish uchun avval xodimni barcha qurilmalardan o‘chiring.');
        }
    },
],
```

`messages()` ga ixtiyoriy emas — `$fail()` matni yetarli.

`EmployeeController::removeDevices`:

```php
return redirect()
    ->back(fallback: route('admin.employees.show', $employee))
    ->with('success', 'Xodimni qurilmalardan o‘chirish navbatga qo‘yildi.');
```

Show testga `->from(route('admin.employees.show', $employee))` qo‘shing agar redirect assert sindi.

- [ ] **Step 4: Run tests — expect pass**

```bash
php artisan test --filter=EmployeeAdminTest
```

Expected: PASS.

---

### Task 2: Frontend — shared panel + Form card lock

**Files:**
- Create: `resources/js/Components/EmployeeDevicesPanel.jsx`
- Modify: `resources/js/Pages/Admin/Employees/Show.jsx`
- Modify: `resources/js/Pages/Admin/Employees/Form.jsx`

**Interfaces:**
- Consumes: `employee.devices` from Task 1; `route('admin.employees.remove-devices')`
- Produces: `EmployeeDevicesPanel({ employeeId, devices })` shared component

- [ ] **Step 1: Extract panel**

`Show.jsx` dagi `EmployeeDevicesPanel` funksiyasini to‘liq ko‘chirib `resources/js/Components/EmployeeDevicesPanel.jsx` ga joylashtiring:

```jsx
import { router } from '@inertiajs/react';
import { App as AntApp, Button, Checkbox, Input, Select } from 'antd';
import { useMemo, useState } from 'react';
import { route } from '../routes';

export default function EmployeeDevicesPanel({ employeeId, devices }) {
    // ... Show.jsx dagi mavjud implementatsiya o‘zgarishsiz
}
```

`Show.jsx` dan panel funksiyasini olib tashlang va:

```jsx
import EmployeeDevicesPanel from '../../../Components/EmployeeDevicesPanel';
```

Show sahifa xatti-harakati o‘zgarmasligi kerak.

- [ ] **Step 2: Wire Form.jsx**

```jsx
import EmployeeDevicesPanel from '../../../Components/EmployeeDevicesPanel';
```

Edit rejimida:

```jsx
const linkedDevices = employee?.devices ?? [];
const cardLocked = isEdit && linkedDevices.length > 0;
```

Karta `FormField` ichida:

```jsx
<FormField label="Karta" error={errors.card} required>
    <Space.Compact className="w-full">
        <Input
            size="large"
            value={data.card}
            status={errors.card ? 'error' : undefined}
            inputMode="numeric"
            maxLength={14}
            placeholder="1–14 xonali son"
            disabled={cardLocked}
            onChange={(e) => {
                const digits = e.target.value.replace(/\D/g, '').slice(0, 14);
                setData('card', digits);
            }}
        />
        <Button
            size="large"
            icon={<ReloadOutlined />}
            title="Yangi raqam generatsiya qilish"
            disabled={cardLocked}
            onClick={() => setData('card', generateCardNumber())}
        />
    </Space.Compact>
    {cardLocked ? (
        <p className="mt-1 text-xs font-medium text-ink-muted">
            Karta raqamini o‘zgartirish uchun avval xodimni qurilmalardan o‘chiring.
        </p>
    ) : null}
</FormField>
```

`FormCard` ichidagi grid dan keyin (yoki `</FormCard>` dan oldin, `isEdit` bo‘lsa):

```jsx
{isEdit ? (
    <div className="mt-6">
        <EmployeeDevicesPanel
            employeeId={employee.id}
            devices={linkedDevices}
        />
    </div>
) : null}
```

Panel `FormCard` ichidagi `<form>` tashqarisida bo‘lishi mumkin — `FormCard` strukturasiga qarab: agar submit tugmalari form ichida bo‘lsa, panelni form ostiga, lekin `FormCard` wrapper ichiga qo‘ying (yoki `FormCard` children oxiriga). Nested form bo‘lmasligi uchun panelda `<form>` yo‘qligini tekshiring (hozir button + router.post — OK).

- [ ] **Step 3: Manual smoke (dev)**

1. Qurilmaga bog‘langan xodimni edit qiling → qurilmalar ko‘rinadi, karta disabled.
2. Qurilmadan o‘chiring → (queue sync) sahifa yangilanadi, karta ochiladi.
3. Kartani o‘zgartirib saqlang → OK.
4. Show sahifa panel hali ishlaydi.

- [ ] **Step 4: Full related tests**

```bash
php artisan test --filter=EmployeeAdminTest
```

Expected: PASS.

---

## Spec coverage check

| Spec item | Task |
|-----------|------|
| Edit da devices ko‘rsatish | 1 + 2 |
| Edit da o‘chirish | 2 (shared panel) + 1 (`back`) |
| Karta UI lock | 2 |
| Backend card reject | 1 |
| Create o‘zgarmaydi | 2 (`isEdit` gate) |
| Show panel reuse | 2 |
| Tests | 1 |

## Execution

Plan tayyor. Ikki yo‘l:

1. **Subagent-Driven (tavsiya)** — har task uchun alohida subagent
2. **Inline Execution** — shu sessionda ketma-ket

Qaysini tanlaysiz?
