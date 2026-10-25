# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Biocontrol** — biometric attendance and access-control admin panel. Laravel 12 + Inertia.js + React 19 (JSX), Tailwind + Ant Design 6, integrated with Hikvision Face ID devices over ISAPI. The displayed app name comes from `APP_NAME` (default `EIdora`); the system is called Biocontrol in code and docs.

**All user-facing strings, flash messages, labels, and most code comments are in Uzbek.** Match that when adding features — don't introduce English UI text.

## Commands

```bash
composer dev
```

Runs `php artisan serve` + `queue:listen` + `schedule:work` + `npm run dev` concurrently. The queue worker matters: removing an employee from devices after the employee is deleted, and removing a face after it is cleared on edit, are queued jobs and silently do nothing without it. Sync and transfer themselves run synchronously inside the HTTP request (see Hikvision below).

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
- `devices:stream-events` — long-running realtime alertStream listener; **run in its own terminal**. The scheduler only ever starts it as `devices:stream-events --ensure` (a no-op while a manager is alive), and only when `HIKVISION_ALERT_STREAM_AUTOSTART=true` — never schedule it without `--ensure` (see the comment at the bottom of `routes/console.php`)
- `devices:pull-employees {device?}` — import employees from a device
- `employees:notify-expiry`, `telegram:daily-digest`, `telegram:set-webhook {url?}`
- `turnstile:pull {--for=0} {--since=} {--rescan}` — reads new rows from the turnstile system's MySQL table `expo.gecishareketleri` (connection `turnstile`, `TURNSTILE_DB_*`, read-only) by `submission_id` cursor and feeds them to `ReportService` (`kartid` = `employees.card`, `cihaz_yon` g/c = entry/exit; rejected attempts and unknown cards are skipped). Scheduled as `turnstile:pull --for=55` every minute when `TURNSTILE_ENABLED=true`. `tests/TestCase.php` forces the `turnstile` connection to in-memory SQLite
- `innoweek:import {path} {--department=} {--dry-run}` — loads Innoweek site participants (`users` + `numbers` from its SQL dump) as **visitors** (`employees.is_visitor`, see "Event visitors" below). The dump is parsed as text by `App\Services\Innoweek\SqlDumpReader` (never executed); card = `numbers.full_number`, group from `users.profession_id` → `professions.name_uz` (Ishtirokchi, Homiy, OAV (Media); active professions are pre-created in id order), upsert by card so re-running is safe; a staff employee with the same card is never overwritten
- `innoweek:sync {--for=0} {--full}` — pulls new/changed participants from the Innoweek API (`GET /api/biocontrol/participants`, Bearer `INNOWEEK_API_TOKEN`, keyset cursor cached in `innoweek.sync.cursor`) through the same `InnoweekImportService::upsertParticipants()` as the dump import. Real time: Innoweek also pushes each participant the moment a card is assigned to `POST /api/webhooks/innoweek/participants` (same Bearer token, `InnoweekWebhookController` → `InnoweekSyncService::upsertItems()`); polling stays as the fallback. Scheduled as `innoweek:sync --for=55` every minute when `INNOWEEK_SYNC_ENABLED=true`. Both import and sync default to the `INNOWEEK_DEPARTMENT` department. The Innoweek-side controller lives in `docs/innoweek-api.md` (spec: `docs/superpowers/specs/2026-10-25-innoweek-api-sync-design.md`). `turnstile:pull` remembers unknown-card passes (`turnstile.pull.unknown`, last 5000) and records them once sync brings the card in

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

`Employee`, `Report` and `Group` boot `BelongsToDepartmentScope` as a **global scope**: any user whose role is not `super-admin` (`User::isDepartmentScoped()`) only ever sees rows matching their `department_id`, and sees nothing if their `department_id` is null. `Note` has no `department_id`; it boots `BelongsToEmployeeDepartmentScope`, which applies `whereHas('employee')` so the employee's scope decides. Consequences:

- Cross-department lookups need `withoutGlobalScopes()` (e.g. `Employee::generateUniqueCard()`). Device imports are system-level facts and bypass the scope even when a scoped user starts them: `ReportService` resolves the employee and de-duplicates unscoped, and `EmployeePullService` finds existing cards unscoped (but skips, rather than edits, another department's employee).
- The `exists:` validation rule queries the table directly and **ignores global scopes** — validate references to scoped models with a closure over `Model::query()` (see `StoreNoteRequest`).
- New models holding a `department_id` should probably add the same scope; models that belong to an employee should use `BelongsToEmployeeDepartmentScope`.
- Store/update requests merge the scoped department via `App\Http\Requests\Concerns\MergesScopedDepartmentId`.

### Event visitors

Employees with `is_visitor = true` (event guests such as Innoweek participants) exist only so device events resolve to a person on `/admin/live` and `/admin/live/stats`. `Employee` boots `ExcludesVisitorsScope` and `Report` boots `ExcludesVisitorReportsScope`, so by default visitors and their passes are invisible everywhere — employees, sync, attendance, reports, notes, dashboard, Telegram, expiry alerts. Only `LiveMonitorService`, `VisitStatsService`, `LiveMonitorController@face` and the department/schedule "has employees" delete guards opt back in with `->withVisitors()` (on both `Employee` and `Report` queries, including `whereHas('reports', …)` and eager-load closures). New pages get the hidden-by-default behaviour for free; don't add `withVisitors()` outside the live pages. Spec: `docs/superpowers/specs/2026-10-24-event-visitors-design.md`.

Hall screens can also be opened **without login** at `/screen/{token}` and `/screen/{token}/stats` (`LIVE_SCREEN_TOKEN`, `config/live.php`; wrong/empty token → 404 via `VerifyLiveScreenToken`, which also drops `{token}` from the route parameters). They reuse `LiveMonitorController` and the same pages with a `screen` prop (public links, no "back to panel"); with no user there is no department scope, so they show everything.

Admins with `'Statistika qo‘shish'` (`live-adjust`) can add manual entries for a given day at `/admin/live/adjustments` (`VisitAdjustment`, department-scoped). `VisitAdjustmentService::totals()` is added on top of the real counts in `LiveMonitorService` and `VisitStatsService` only: entered, men/women split 70/30 at creation, all local, the chosen group (default "Ishtirokchi"); "last hour" counts additions created in the last 60 minutes. Inside/exited, registered and the pass lists stay real. Spec: `docs/superpowers/specs/2026-10-25-visit-adjustments-design.md`.

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

**Employees never auto-sync to devices.** Creating or editing an employee only writes the database; pushing to hardware happens exclusively through Admin → Sinxronizatsiya or a device's Sync action. Preserve this — flash messages tell operators so explicitly.

Sync, transfer, device Clear and "remove from device" run **synchronously in the request** (`set_time_limit(0)`) and report counts back in the flash. The Sinxronizatsiya page calls `POST /admin/sync/transfer-one` once per employee × device pair from a progress modal — the one place JSX uses raw `fetch`. Only `EmployeeService::delete()` and face removal in `update()` go through the queue (`RemoveHikvisionUsersJob`, `RemoveHikvisionFacesJob`); the other jobs in `app/Jobs/Hikvision/` and the `queue*` helpers on `EmployeeSyncService` are currently unused.

Events arrive through **four independent channels**, all converging on `ReportService`:

1. Webhook `POST /api/webhooks/hikvision/events/{secret?}` → `WebhookController` (secret via header, query, or path; device IP can be trusted instead)
2. `devices:stream-events` — persistent alertStream connection
3. `devices:pull-events` — scheduled polling fallback
4. `turnstile:pull` — passes from the separate turnstile system (Expo Panel) database, not Hikvision

Duplicates are expected and de-duplicated inside `ReportService::createFromAccessEvent()` by `(occurred_at, device_id, employee)`.

## Reports

`AccessControllerEvent` (raw device event) → `ReportService` → `Report` (normalized entry/exit row). Read-side lives in `ReportQueryService` + `App\Services\Reports\AttendanceCalculator`, driven by the `AttendanceReportType` enum (Entry, Exit, Late, LeftEarly, Present, Absent, WorkTime, Individual). Each type declares its own column set, whether it `requiresSchedule()`, and whether it `requiresBoundedDates()`.

`AttendanceCalculator` handles night shifts by shifting the moment back 12 hours (`shiftMoment()`) so an overnight shift attributes to the correct work date — keep that in mind when touching late/early/worked-seconds math. `Note` records (`NoteType`: CameLate / LeftEarly / Absent) act as excuses and are joined in to suppress or annotate rows.

The **Davomat** page (`/admin/attendance`, permission `'Davomat'`) is a separate employee × work-day grid built by `App\Services\Attendance\AttendanceGridService` over `AttendancePeriod` (week / 2 weeks / month). Its rules differ from the Reports page on purpose: work days are Monday–Friday only, 1 hour of lunch is subtracted from worked and planned time, any `Note` on a day makes it "Sababli", and days outside an employee's `valid_from_dt`..`valid_to_dt` are not counted. Notes added from this page ("Ariza berish") go through `POST /admin/attendance/notes` and need the `'Izohlar'` permission too. Spec: `docs/superpowers/specs/2026-10-23-attendance-grid-design.md`.

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
