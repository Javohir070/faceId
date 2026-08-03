# Employee Notes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xodimlar uchun `Note` (kechikdi/erta ketdi/kelmadi) CRUD, xodim sahifasidan qo‘shish, Telegram diggestda ruxsatli late/absent ni hisobdan chiqarish.

**Architecture:** `NoteType` backed int enum; `notes` jadvali unique `(employee_id, dt, type)`; Employee-style Repository→Service→Controller→Inertia; diggest `TelegramReportService` da Notes bo‘yicha exclude.

**Tech Stack:** Laravel 12, PHP 8.2 backed enums, Inertia React, Spatie permission, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-03-employee-notes-design.md`

## Global Constraints

- Type DB: `1=came_late`, `2=left_early`, `3=absent`; UI: Kechikdi / Erta ketdi / Kelmadi.
- `dt` faqat `date`; `note` majburiy.
- Unique: bir xodim + bir sana + bir type = bitta yozuv.
- Permission nomi: `Izohlar` (`menu_permissions.resources.notes`).
- Diggest: `came_late` → late exclude; `absent` → absent exclude; `left_early` diggestda hisoblanmaydi.
- Show page Notes uchun shart emas — Index + Form yetarli; Employee Show da ro‘yxat + create.

## File map

| File | Role |
|------|------|
| `app/Enums/NoteType.php` | backed int enum + label |
| `database/migrations/*_create_notes_table.php` | schema |
| `app/Models/Note.php` | model |
| `app/Models/Employee.php` | `notes()` |
| `app/Interfaces/NoteRepositoryInterface.php` | contract |
| `app/Repositories/NoteRepository.php` | query/CRUD |
| `app/Services/NoteService.php` | app logic |
| `app/Http/Controllers/NoteController.php` | Inertia CRUD |
| `app/Http/Requests/StoreNoteRequest.php` / `UpdateNoteRequest.php` | validation |
| `resources/js/Pages/Admin/Notes/Index.jsx` / `Form.jsx` | UI |
| `resources/js/Pages/Admin/Employees/Show.jsx` | notes block |
| `config/menu_permissions.php`, `adminNav.js`, `HandleInertiaRequests.php`, `routes.js`, `web.php` | menu/routes |
| `app/Services/Telegram/TelegramReportService.php` | digest exclude |

---

### Task 1: Enum, migration, models

**Files:**
- Create: `app/Enums/NoteType.php`
- Create: `database/migrations/2026_08_03_130000_create_notes_table.php`
- Create: `app/Models/Note.php`
- Create: `database/factories/NoteFactory.php`
- Modify: `app/Models/Employee.php`
- Test: `tests/Unit/NoteTypeTest.php`, `tests/Feature/Admin/NoteModelTest.php`

**Interfaces:**
- Produces: `NoteType::CameLate=1`, `LeftEarly=2`, `Absent=3`; `NoteType::label(): string`; `Note` fillable/casts; `Employee::notes(): HasMany`

- [ ] **Step 1: Write failing tests**

`tests/Unit/NoteTypeTest.php`:

```php
public function test_labels_and_values(): void
{
    $this->assertSame(1, NoteType::CameLate->value);
    $this->assertSame(2, NoteType::LeftEarly->value);
    $this->assertSame(3, NoteType::Absent->value);
    $this->assertSame('Kechikdi', NoteType::CameLate->label());
    $this->assertSame('Erta ketdi', NoteType::LeftEarly->label());
    $this->assertSame('Kelmadi', NoteType::Absent->label());
}
```

`tests/Feature/Admin/NoteModelTest.php` — RefreshDatabase: create note, assert cast to NoteType; assert unique violation on duplicate employee_id+dt+type.

- [ ] **Step 2: Run — expect FAIL**

`php artisan test --filter=NoteTypeTest`

- [ ] **Step 3: Implement enum, migration, Note, factory, Employee::notes**

```php
enum NoteType: int
{
    case CameLate = 1;
    case LeftEarly = 2;
    case Absent = 3;

    public function label(): string
    {
        return match ($this) {
            self::CameLate => 'Kechikdi',
            self::LeftEarly => 'Erta ketdi',
            self::Absent => 'Kelmadi',
        };
    }
}
```

Migration: `employee_id` FK cascade, `type` tinyInteger, `dt` date, `note` text, unique `['employee_id','dt','type']`.

Note model: casts `type` => NoteType::class, `dt` => date; belongsTo Employee.

- [ ] **Step 4: Run tests — PASS**

- [ ] **Step 5: Commit** `Add Note model, NoteType enum, and migration`

---

### Task 2: Repository, Service, FormRequests, binding

**Files:**
- Create: Interface, Repository, Service, Store/Update NoteRequest
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Admin/NoteRepositoryTest.php` (yoki to‘g‘ridan Feature CRUD Task 3 da)

**Interfaces:**
- Produces:
  - `NoteRepositoryInterface::filteredQuery(array $filters): Builder`
  - `paginate(int $perPage, array $filters): LengthAwarePaginator`
  - `create(array $data): Note`, `update(Note, array): Note`, `delete(Note): void`
  - Filters: `employee_id`, `type`, `date_from`, `date_to`, `search` (note text / employee name)
  - `NoteService` wraps repo + `employeesForForm(): Collection`

- [ ] **Step 1: Write failing service/repo test** — create + unique filter by employee/type/date

- [ ] **Step 2: Implement + bind in AppServiceProvider**

Store/Update rules:
```php
'employee_id' => ['required', 'exists:employees,id'],
'type' => ['required', Rule::enum(NoteType::class)],
'dt' => ['required', 'date'],
'note' => ['required', 'string'],
```
Unique Rule: `Rule::unique('notes')->where(fn ($q) => $q->where('employee_id',...)->where('type',...))->ignore($note)`

authorize: `$this->user()?->can(config('menu_permissions.resources')['notes'])`

- [ ] **Step 3: Tests PASS + Commit** `Add Note repository, service, and form requests`

---

### Task 3: Controller, routes, menu, permissions wiring

**Files:**
- Create: `app/Http/Controllers/NoteController.php`
- Modify: `routes/web.php`, `config/menu_permissions.php`, `HandleInertiaRequests.php`, `resources/js/config/adminNav.js`, `resources/js/routes.js`
- Test: `tests/Feature/Admin/NoteAdminTest.php`

**Interfaces:**
- Produces: `admin.notes.*` resource routes; Inertia pages props; `can.notes`

- [ ] **Step 1: Write NoteAdminTest** (mirror EmployeeAdminTest):
  - authorized CRUD
  - unauthorized 403
  - unique validation fails on duplicate

- [ ] **Step 2: Run — FAIL (404)**

- [ ] **Step 3: Implement NoteController** — index/create/store/edit/update/destroy; index filters; Form props: `employees`, `types` (value/label list from NoteType cases)

Add `'notes' => 'Izohlar'` to menu_permissions after employees.

HandleInertiaRequests: share `can.notes`.

adminNav: item under employees-group or after employees:
```js
{ key: 'notes', permission: 'notes', href: route('admin.notes.index'), label: 'Izohlar', icon: FormOutlined },
```

routes.js: `...resource('admin.notes', '/admin/notes')`

web.php: `Route::resource('notes', NoteController::class)->middleware('permission:'.$menuPermissions['notes']);`

- [ ] **Step 4: Minimal stub Inertia pages so controller doesn't 500** (yoki Task 4 bilan birga — agar stub bo‘lsa Task 3 test Inertia component assert)

Agar frontend keyinroq: testlarda `assertRedirect` store/update/destroy va index `assertOk` yetarli; Inertia component assert Task 4 da.

- [ ] **Step 5: PASS + Commit** `Add Note admin controller, routes, and menu permission`

---

### Task 4: Frontend Index + Form

**Files:**
- Create: `resources/js/Pages/Admin/Notes/Index.jsx`
- Create: `resources/js/Pages/Admin/Notes/Form.jsx`
- Modify: `resources/js/routes.js` (agar Task 3 da qilinmagan)

**Interfaces:**
- Consumes: Inertia props `notes`, `filters`, `employees`, `types`, `note` (edit)

- [ ] **Step 1: Index** — PageHeader “Izoh qo‘shish”, filters (employee select, type, date_from/to), table columns: Xodim, Tip, Sana, Izoh, actions edit/delete

- [ ] **Step 2: Form** — employee select (create da; edit da disabled yoki o‘zgartirish mumkin), type select, date, note textarea; useForm post/put

- [ ] **Step 3: Feature test Inertia assert** `component('Admin/Notes/Index')` / `Form`

- [ ] **Step 4: Commit** `Add Notes admin Inertia pages`

---

### Task 5: Employee Show — notes block

**Files:**
- Modify: `app/Http/Controllers/EmployeeController.php` `show()` — load notes ordered by dt desc
- Modify: `resources/js/Pages/Admin/Employees/Show.jsx`
- Test: extend EmployeeAdminTest yoki NoteAdminTest — show contains notes; link to `admin.notes.create?employee_id=`

**Interfaces:**
- Create route accepts `?employee_id=` query — Form da prefill

- [ ] **Step 1: Controller create()** — `Request $request`, pass `prefillEmployeeId`

- [ ] **Step 2: Show.jsx** — jadval + button `router.get(route('admin.notes.create'), { employee_id })`

- [ ] **Step 3: Test + Commit** `Show employee notes and prefill create form`

---

### Task 6: Telegram digest exclude + seed note

**Files:**
- Modify: `app/Services/Telegram/TelegramReportService.php`
- Modify: `tests/Feature/Telegram/TelegramDailyDigestTest.php`
- Manual: super-admin ga `Izohlar` permission — seeder `menu_permissions` orqali avtomatik; mavjud DB da `php artisan db:seed` yoki permission sync eslatma

**Interfaces:**
- Consumes: `Note` where dt = day, type in CameLate/Absent

- [ ] **Step 1: Failing test** — late employee with `came_late` note same day → late count 0; absent with `absent` note → absent count 0

- [ ] **Step 2: In digestForDate**, load note employee_ids for day by type; skip those in late/absent loops

```php
$excusedLateIds = Note::query()
    ->whereDate('dt', $start->toDateString())
    ->where('type', NoteType::CameLate)
    ->pluck('employee_id');
```

- [ ] **Step 3: PASS full Note + Telegram filters**

`php artisan test --filter=Note`
`php artisan test --filter=TelegramDailyDigestTest`

- [ ] **Step 4: Commit** `Exclude excused late/absent from Telegram digest`

---

## Spec coverage

| Requirement | Task |
|-------------|------|
| NoteType 1/2/3 + labels | 1 |
| notes table + unique | 1 |
| Admin CRUD + Izohlar permission | 2–4 |
| Employee show + create | 5 |
| Diggest exclude | 6 |

## Manual smoke

1. `php artisan migrate`
2. Super-admin ga `Izohlar` ruxsati (seed yoki UI)
3. `/admin/notes` — izoh qo‘shish
4. Employee show — izohlar ko‘rinadi
5. Diggest: ruxsatli kechikish hisobga kirmasligi
