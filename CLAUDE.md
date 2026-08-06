# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Biocontrol** — biometric attendance and access-control admin panel. Laravel 12 + Inertia.js + React 19 (JSX), Tailwind + Ant Design 6, integrated with Hikvision Face ID devices over ISAPI. The displayed app name comes from `APP_NAME` (default `EIdora`); the system is called Biocontrol in code and docs.

**All user-facing strings, flash messages, labels, and most code comments are in Uzbek.** Match that when adding features — don't introduce English UI text.

## Commands

```bash
composer dev
```

Runs `php artisan serve` + `queue:listen` + `schedule:work` + `npm run dev` concurrently. The queue worker matters: device sync, transfers, and removals are queued jobs and silently do nothing without it.

| Task | Command |
|------|---------|
| Tests | `composer test` (clears config cache first — plain `php artisan test` can pick up cached MySQL config) |
| Single test file | `php artisan test tests/Feature/Admin/EmployeeAdminTest.php` |
| Single test method | `php artisan test --filter=test_employee_can_be_created` |
| Format | `vendor/bin/pint` (default Laravel preset, no `pint.json`) |
| Build assets | `npm run build` |
| Migrate | `make m` / fresh+seed: `make m-f` |
| Clear caches | `make o-c` |

Artisan commands specific to this project:

- `devices:ping` — background online/offline check (scheduled)
- `devices:pull-events` — pull access events from devices (scheduled, backup channel)
- `devices:stream-events` — long-running realtime alertStream listener; **run in its own terminal, never via the scheduler** (see the comment at the bottom of `routes/console.php`)
- `devices:pull-employees {device?}` — import employees from a device
- `employees:notify-expiry`, `telegram:daily-digest`, `telegram:set-webhook {url?}`

## Request layering

Every admin resource follows the same chain — don't shortcut it:

```
routes/web.php  →  Controller  →  Service  →  Repository (interface)  →  Model
                     ↑ FormRequest        ↑ bound in AppServiceProvider
                     ↓ Inertia::render / redirect()->with('success'|'error')
```

- Repository interfaces live in `app/Interfaces/`, implementations in `app/Repositories/`, and the binding map is the `$repositories` array in `app/Providers/AppServiceProvider.php`. A new repository must be registered there.
- Controllers inject services via constructor promotion; they never touch Eloquent for writes.
- Validation always lives in a `FormRequest` (`app/Http/Requests/`), except list-filter validation which controllers do inline via a private `filterRules()` method (see `EmployeeController`).
- Responses to Inertia pages are `Inertia::render('Admin/<Resource>/<Page>', [...])`; mutations redirect back with a `success` or `error` flash.

## Permissions — the wiring ritual

`config/menu_permissions.php` is the single source of truth. Permission names are **Uzbek display strings** (`'Xodimlar'`, `'Qurilmalar'`), not slugs. Adding or renaming a permission-guarded resource means touching all of these:

1. `config/menu_permissions.php` — add the key
2. `routes/web.php` — `->middleware('permission:'.$menuPermissions['<key>'])`
3. `app/Http/Middleware/HandleInertiaRequests.php` — add to the shared `can` map
4. `resources/js/config/adminNav.js` — sidebar entry (filtered by `can`)
5. `resources/js/routes.js` — URL builders (see below)
6. `database/seeders/DatabaseSeeder.php` picks it up automatically from config, but existing installs need a **migration** that creates the permission row (see `2026_08_03_161000_add_groups_permission.php` for the pattern)

`super-admin` gets every permission; the `user` role gets only `home`.

## Department scoping

`Employee` and `Report` boot `BelongsToDepartmentScope` as a **global scope**: any user whose role is not `super-admin` (`User::isDepartmentScoped()`) only ever sees rows matching their `department_id`, and sees nothing if their `department_id` is null. Consequences:

- Cross-department lookups need `withoutGlobalScopes()` (e.g. `Employee::generateUniqueCard()`).
- New models holding a `department_id` should probably add the same scope.
- Store/update requests merge the scoped department via `App\Http\Requests\Concerns\MergesScopedDepartmentId`.

## Hikvision integration

Full reference: [docs/hikvision-integration.md](docs/hikvision-integration.md).

Device credentials (IP, port, https, user, encrypted password) live in the `devices` table via Admin → Qurilmalar — **not in `.env`**. `config/hikvision.php` only holds timeouts, intervals, and webhook/ISAPI defaults.

```
Controller / Console command
        ↓
EmployeeSyncService · HikvisionDeviceService · HikvisionUserService · EmployeePullService
        ↓ (queued jobs in app/Jobs/Hikvision/)
IsapiClient          ← HTTP Digest auth wrapper around Laravel Http
        ↓
Hikvision device (ISAPI)
```

**Employees never auto-sync to devices.** Creating or editing an employee only writes the database; pushing to hardware happens exclusively through Admin → Sinxronizatsiya or a device's Sync action, both of which dispatch queue jobs. Preserve this — flash messages tell operators so explicitly.

Events arrive through **three independent channels**, all converging on `ReportService`:

1. Webhook `POST /api/webhooks/hikvision/events/{secret?}` → `WebhookController` (secret via header, query, or path; device IP can be trusted instead)
2. `devices:stream-events` — persistent alertStream connection
3. `devices:pull-events` — scheduled polling fallback

Duplicates are expected and de-duplicated inside `ReportService::createFromAccessEvent()` by `(occurred_at, device_id, employee)`.

## Reports

`AccessControllerEvent` (raw device event) → `ReportService` → `Report` (normalized entry/exit row). Read-side lives in `ReportQueryService` + `App\Services\Reports\AttendanceCalculator`, driven by the `AttendanceReportType` enum (Entry, Exit, Late, LeftEarly, Present, Absent, WorkTime, Individual). Each type declares its own column set, whether it `requiresSchedule()`, and whether it `requiresBoundedDates()`.

`AttendanceCalculator` handles night shifts by shifting the moment back 12 hours (`shiftMoment()`) so an overnight shift attributes to the correct work date — keep that in mind when touching late/early/worked-seconds math. `Note` records (`NoteType`: CameLate / LeftEarly / Absent) act as excuses and are joined in to suppress or annotate rows.

## Frontend

- **There is no Ziggy.** `resources/js/routes.js` is a hand-maintained map of route names → URL builders and throws on unknown names. Every new named route used from JSX must be added there.
- Nav/permission gating: `getVisibleNavGroups(can)` in `resources/js/config/adminNav.js`.
- Theme: `AdminThemeProvider` syncs `html[data-admin-theme]` with Ant Design tokens; CSS variables live in `resources/css/app.css` and `tailwind.config.js`. Both sides must stay in sync.
- Layout with Tailwind, interactive widgets with Ant Design. Icons come only from `@ant-design/icons` — no Feather/Lucide, no additional UI libraries.
- Navigate with Inertia `Link` / `router` / `useForm`; never raw `<a>` or `fetch` for internal routes.
- Reuse the shared components (`PageHeader`, `TableShell`, `FormCard`, `FormField`, `ShowPage`, `Pagination`, `FlashMessages`, `FaceCaptureField`) instead of re-writing CRUD markup.
- Never edit generated files under `public/build`.

Design rules are specified in [DESIGN.md](DESIGN.md) and [PRODUCT.md](PRODUCT.md), enforced by `.cursor/rules/impeccable-frontend.mdc` and the `.impeccable/` detector.

## Testing

`tests/TestCase.php` deliberately **refuses to run against anything but SQLite** — it deletes `bootstrap/cache/config.php`, forces `sqlite/:memory:`, and throws if `RefreshDatabase` is about to hit a database named like production. Don't weaken this; if a test fails to boot, fix the config, not the guard.

Helpers: `Tests\Concerns\InteractsWithPermissions` (`createUserWithPermissions()` grants everything + `super-admin` so global scopes are bypassed; `createDepartmentUserWithPermissions($departmentId)` for scope tests) and `Tests\Concerns\CreatesTelegramAdmin`.

## Plans and specs

Feature work in this repo is written up first in `docs/superpowers/specs/` (design) and `docs/superpowers/plans/` (implementation), named `YYYY-MM-DD-<feature>.md`. Check there before implementing something that looks like it was already designed.
