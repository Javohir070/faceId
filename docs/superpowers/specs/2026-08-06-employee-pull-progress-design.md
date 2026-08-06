# Xodim tortish (Employee Pull) — per-device progress va aniq xatolar

## Muammo

"Xodim tortish" (qurilmadan xodimlarni ISAPI orqali bazaga import qilish) hozir ikkita joyda mavjud, ikkalasi ham muammoli:

1. **Bulk sahifa** — `Admin → Xodimlar → Xodim tortish` ([Pull.jsx](../../../resources/js/Pages/Admin/Employees/Pull.jsx)). Bir nechta qurilma tanlanadi, `POST admin.employees.pull.store` ga yuboriladi, `EmployeeController::pullStore()` esa **sinxron** ravishda, bitta HTTP request ichida, tanlangan qurilmalarning barchasini ketma-ket to'liq tortib bo'ladi (`EmployeePullService::pullDevices()`). Har bir xodim uchun qo'shimcha 1–3 ta HTTP so'rov (yuz rasmi uchun) ketishi mumkin — ko'p xodimli qurilmalarda bu umumiy PHP `max_execution_time` yoki veb-server timeout'idan oshib ketishi, foydalanuvchiga tushunarsiz xato yoki "osilib qolgan" sahifa ko'rinishida namoyon bo'lishi mumkin. Bundan tashqari, bu sahifa tanlanadigan qurilmalarni `is_online` bo'yicha filtermaydi (faqat `has_credentials`) — offline qurilma tanlansa, unga ulanish urinishi ISAPI connect/timeout limitigacha (5–10s) kutadi, umumiy vaqtni yanada uzaytiradi.
2. **Bitta qurilma amali** — `Admin → Qurilmalar → {device} → Xodimlarni tortish` ([Devices/Show.jsx](../../../resources/js/Pages/Admin/Devices/Show.jsx)). `DeviceActionController::pullEmployees()` — bitta qurilmaga cheklangani va oldindan `is_online` tekshiruvi borligi uchun xavfi kamroq, lekin natija faqat umumiy flash xabar (`success`/`error`) sifatida ko'rinadi.

Ikkala holatda ham xato xabari faqat sonlardan iborat (`N qurilma xato`, `N xodim xato`) — **qaysi qurilma** va **nima sababdan** xato berganini ko'rish uchun `storage/logs/laravel.log`ga qarash kerak. Admin panelda bu ko'rinmaydi.

### Kodni tekshirish natijasi: mavjud andoza

`Admin → Sinxronizatsiya` sahifasi ([Sync/Index.jsx](../../../resources/js/Pages/Admin/Sync/Index.jsx)) xuddi shu muammoni (uzoq Hikvision HTTP tsikli, HTTP request ichida) allaqachon hal qilgan — lekin Laravel queue job orqali emas. `app/Jobs/Hikvision/` dagi `ShouldQueue` joblar (`TransferEmployeesJob`, `SyncDeviceEmployeesJob` va h.k.) amalda **ishlatilmaydi** (faqat `RemoveHikvisionUsersJob`/`RemoveHikvisionFacesJob` xodim o'chirishda chaqiriladi). Buning o'rniga frontend **har bir birlik (device×employee) uchun alohida JSON so'rov** yuboradi (`admin.sync.transfer-one`), ketma-ket, jonli progress modal bilan (`SyncProgressModal`). Bulk `admin.sync.transfer` route hali mavjud, lekin frontend uni endi chaqirmaydi — amaldagi ishlayotgan andoza aynan per-item + progress modal.

Shu loyihada ushbu ikkita "Xodim tortish" oqimini o'sha isbotlangan andozaga moslashtiramiz.

## Maqsad

- Ko'p qurilma/xodim bo'lganda timeout xavfini yo'qotish.
- Har bir qurilma bo'yicha **aniq** natija/xato matnini foydalanuvchiga jonli ko'rsatish.
- Ikkala pull joyini (bulk sahifa + bitta qurilma amali) bitta umumiy backend logikasiga va bitta umumiy frontend komponentiga birlashtirish.
- Yangi Laravel queue infratuzilmasi qurmaslik — mavjud, isbotlangan "per-item + progress modal" andozasini takrorlash.

## Nomaqsad

- `EmployeePullService::pullDevice()` ichki mantig'ini o'zgartirish (u to'g'ri ishlayapti, testlari bor).
- CLI (`devices:pull-employees`) yoki `pullAllDevices()`ni o'zgartirish — CLI kontekstida timeout muammosi yo'q.
- Xodim darajasidagi (employee-level) xatolarni UI'da alohida ko'rsatish — faqat qurilma darajasida (device-level) aniqlik yetarli; xodim darajasi hozirgidek `laravel.log`da qoladi.

## Arxitektura

```
Pull.jsx (bulk)          Devices/Show.jsx (bitta qurilma)
        │                         │
        └──────────┬──────────────┘
                    ▼
      POST /admin/employees/pull-one   { device_id }
                    │
      EmployeeController::pullOne()
                    │
      EmployeePullService::pullDevice($device)   ← o'zgarmaydi
                    │
                 JSON javob
      { status, device_id, device_name, device_ip,
        created, updated, linked, skipped, faces, failed, message }
```

Har ikki frontend sahifa ham xuddi shu endpointga, xuddi shu formatda so'rov yuboradi va xuddi shu umumiy `ProgressModal` komponenti orqali natijani ko'rsatadi (bulk sahifada N ta qurilma ustida loop, bitta qurilma sahifasida bitta chaqiruv, total=1).

## Backend o'zgarishlari

### Yangi: `EmployeeController::pullOne()`

- Request: yangilangan `PullEmployeesRequest` — `device_id` (required, integer, `exists:devices,id`).
- Authorize: `employees-pull` permission (mavjud `config/menu_permissions.php` kaliti).
- Mantiq:
  1. `Device::findOrFail($request->device_id)`
  2. `! $device->hasIsapiCredentials()` → `{status: 'skipped', message: 'Login/parol sozlanmagan', ...$base}`
  3. `! $device->is_online` → `{status: 'skipped', message: 'Qurilma ulanmagan', ...$base}`
  4. `try { $result = $employeePullService->pullDevice($device); } catch (\Throwable $e) { return {status:'failed', message: $e->getMessage(), ...$base}; }`
  5. Status klassifikatsiyasi:
     - `$result['failed'] > 0 && $result['created']+$result['updated']+$result['linked']+$result['faces'] === 0` → `'failed'`
     - `$result['failed'] > 0` → `'partial'`
     - aks holda → `'success'`
  6. Javob har doim `device_id, device_name, device_ip` + barcha `$result` kalitlarini o'z ichiga oladi; `message` — status matnli tavsifi (masalan `'5 yangi, 2 yangilangan, 1 yuz'` yoki xato matni).

### Route

```php
Route::post('employees/pull-one', [EmployeeController::class, 'pullOne'])
    ->middleware('permission:'.$menuPermissions['employees-pull'])
    ->name('employees.pull.pull-one');
```

**O'chiriladi:**
- `POST employees/pull` (`employees.pull.store`) va `EmployeeController::pullStore()`
- `POST devices/{device}/pull-employees` (`devices.pull-employees`) va `DeviceActionController::pullEmployees()`
- `EmployeePullService::pullDevices(array $deviceIds)` (endi hech kim chaqirmaydi)

**O'zgarishsiz qoladi:**
- `EmployeePullService::pullDevice()`, `pullAllDevices()`
- `EmployeeController::pull()` (GET, tanlash sahifasi)
- `PullDeviceEmployeesCommand` (CLI)

### ⚠️ Ataylab tanlangan ruxsat o'zgarishi

Bugun `devices/{device}/pull-employees` — `devices` permission (`Qurilmalar`) bilan himoyalangan; `employees/pull` — `employees-pull` permission (`Xodimlarni tortish`) bilan. Yangi yagona route faqat `employees-pull`dan foydalanadi. **Natija:** faqat `Qurilmalar` huquqiga ega-yu, `Xodimlarni tortish` huquqiga ega bo'lmagan rol — qurilma sahifasidagi "Xodimlarni tortish" tugmasini yo'qotadi. `super-admin` ta'sirlanmaydi (barcha huquqlarga ega). Joriy rol/permission tayinlashlarini (`database/seeders/DatabaseSeeder.php`, mavjud rollar) joylashtirishdan oldin tekshirib chiqish tavsiya etiladi.

## Frontend o'zgarishlari

### Yangi umumiy komponentlar

- `resources/js/Components/ProgressModal.jsx` — `Sync/Index.jsx`dagi `SyncProgressModal`dan ajratib olinadi, `title` prop qo'shiladi (generic props: `open, current, total, logs, done, cancelled, onClose, onCancel, title`). `Sync/Index.jsx` shu componentga o'tadi (`title="Sinxronizatsiya jarayoni"`), `SyncProgressModal` local funksiyasi o'chiriladi.
- `resources/js/utils/xsrf.js` — `getXsrfToken()` `Sync/Index.jsx`dan ko'chiriladi, ikkala sahifa import qiladi.

### `Pull.jsx` (bulk sahifa)

- "Tortib olish" tugmasi endi `runPullJobs()` ni chaqiradi (Sync'dagi `runTransferJobs` andozasi bo'yicha):
  - Tanlangan qurilmalar ustida ketma-ket loop, har biri uchun `fetch(route('admin.employees.pull.pull-one'), {device_id})`.
  - `ProgressModal` (`title="Xodim tortish jarayoni"`) jonli current/total va log qatorlarini ko'rsatadi.
  - Log format: `` `IP: ${device_ip} (${device_name}): ${created} yangi, ${updated} yangilangan, ${faces} yuz` `` (success/partial) yoki `` `IP: ${device_ip} (${device_name}) — Xato: ${message}` `` (failed/skipped).
  - Bekor qilish tugmasi mavjud loop'ni to'xtatadi (Sync'dagi `abortRef` andozasi).
- Jadvalda offline qurilmalar tanlanganda Sync'dagi kabi ogohlantirish banner ko'rsatiladi (`X ta tanlangan qurilma ulanmagan — o'tkazib yuboriladi`), foydalanuvchi loop boshlanishidan oldin biladi.

### `Devices/Show.jsx`

- `runAction()`dagi `pull` case: confirm modal saqlanadi, lekin `onOk` endi `router.post(...)` o'rniga bitta `fetch('admin.employees.pull.pull-one', {device_id})` chaqiradi va natijani `ProgressModal`da (`total=1`) ko'rsatadi — xato bo'lsa aniq matn ko'rinadi, umumiy flash xabar o'rniga.

## Testlar

- **Yangi feature testlar** (`tests/Feature/Admin/EmployeePullOneTest.php` yoki mavjud faylga qo'shiladi): success, offline→skipped, no-creds→skipped, connection exception→failed, qisman xato→partial, permission tekshiruvi.
- **Yangilanadi/o'chiriladi:** `DeviceActionTest.php`dagi `pull-employees` route testlari, bulk `employees/pull` POST testlari (`EmployeePullPageTest.php` bo'lsa) — yangi endpointga ko'chiriladi.
- **O'zgarishsiz:** `EmployeePullServiceTest.php` (`pullDevice()` service darajasida test qilinadi, o'zgarmaydi).
- **Frontend:** JS test suite yo'q loyihada — `composer dev` orqali brauzerda qo'lda tekshiriladi (Pull.jsx bulk oqimi, Show.jsx bitta qurilma oqimi, offline/no-creds holatlari).

## Xavf va cheklovlar

- Bitta qurilmada juda ko'p (minglab) xodim + yuz rasmi bo'lsa, o'sha **bitta** `pull-one` so'rovi hali ham uzoq davom etishi mumkin (bu boshqa qurilmalarni bloklamaydi, chunki har biri alohida so'rov, lekin o'sha bitta qurilma uchun kutish davom etadi). Bu joriy `EmployeePullService::pullDevice()` chegarasi — spec doirasidan tashqarida (Nomaqsad bo'limiga qarang).
