# Eski tizim API’si (`/api/legacy`)

Eski Biocontrol tizimining `api.php` fayliga ulangan tashqi tizimlar yangi tizim bilan ham ishlashi uchun.
So‘rov va javob formati eskisidek: tashqi tizimda faqat manzil almashadi.

- **Manzil:** `POST https://<server>/api/legacy` (GET ham qabul qilinadi, lekin tanasiz u `{"status": false}` qaytaradi).
- **Tana:** JSON, amal `action` maydonida. `Content-Type` shart emas.
- **Javob:** har doim HTTP 200 va `{"status": true|false, ..., "alerts": [...]}`. `alerts` faqat xabar bo‘lsa chiqadi.
  Bo‘sh tana, buzuq JSON yoki noma’lum `action` uchun eskidek `{"status": false}`.
- **Kod:** `App\Http\Controllers\LegacyApiController` → `App\Services\LegacyApiService`, IP filtri `App\Http\Middleware\RestrictLegacyApiIps`.

## Sozlash (.env)

```dotenv
# Tashqi tizim serverining IP manzili (bir nechta bo‘lsa vergul bilan, CIDR ham bo‘ladi)
LEGACY_API_ALLOWED_IPS=203.0.113.5
# add_employee yangi xodim yaratganda beradigan bo‘lim va ish jadvali id’si
LEGACY_API_DEPARTMENT_ID=4
LEGACY_API_SCHEDULE_ID=1
```

`.env` o‘zgargach `php artisan config:clear` (config keshlangan bo‘lsa `php artisan config:cache`).

- `LEGACY_API_ALLOWED_IPS` bo‘sh bo‘lsa API yopiq: hamma so‘rovga `403 {"status": false, "alerts": ["Access denied"]}`.
- IP so‘rovning haqiqiy ulanish manzilidan olinadi. `X-Forwarded-For` sarlavhasiga faqat so‘rov shu serverning o‘zidagi proksidan (127.0.0.1) kelganda ishoniladi, chunki uni istalgan mijoz o‘zi yozib yuborishi mumkin.
  Server oldida Cloudflare kabi tashqi proksi tursa, so‘rovlar proksi IP’sidan keladi va rad etiladi. Bunday holat uchun alohida sozlash kerak bo‘ladi.
- Bo‘lim va jadval faqat **yangi** xodimga beriladi. Karta bazada bor bo‘lsa, xodimning bo‘limi, jadvali va guruhi o‘zgarmaydi.

## Amallar

### add_employee

```json
{"action": "add_employee", "name": "Ali Valiyev", "card": "12345678901234", "template": 1,
 "face": "<base64 JPEG>", "start": "2026-01-01 00:00:00", "end": "2027-12-31 23:59:59"}
```

- Karta (JSHSHIR) bo‘yicha xodim yaratiladi yoki yangilanadi (ism, amal qilish muddati, yuz rasmi).
  `card` berilmasa, eskidek 8 xonali tasodifiy raqam beriladi.
- **Eskidek darhol barcha terminallarga yoziladi.** Yangi tizimdagi «xodim terminalga faqat Sinxronizatsiya orqali boradi» qoidasidan bu yagona istisno.
- `template`: `1` Authorized, `2` Blocked. Yangi tizimda shablon yo‘q: Blocked bo‘lsa amal qilish muddati shu daqiqada tugaydi.
  Terminaldagi foydalanuvchi qoladi, lekin o‘tolmaydi. Bunday xodim uchun «muddati tugadi» Telegram xabari yuborilmaydi.
- `finger`, `fingerprint`, `phone`, `address` kabi maydonlar qabul qilinadi, lekin saqlanmaydi: yangi tizimda barmoq izi yo‘q.
- Javob: `{"status": true, "alerts": ["Saved successfully"]}`.
- Terminal xatosi bo‘lsa, xodim bazada saqlangan bo‘ladi, javob esa:
  `{"status": false, "alerts": [{"status": false, "message": "IP: 10.0.0.8; ID: 15; Error: ..."}]}`.
  Yuz rasmi bo‘lmasa terminalga yozilmaydi va bu ham xato sifatida qaytadi.
- Tekshiruv xatolari: `Enter name`, `Select template`, `Enter start datetime`, `Enter end datetime`.
  Yangilari: `Invalid template`, `Invalid card number` (20 belgidan uzun), `Invalid start datetime`, `Invalid end datetime`,
  `Invalid face image`, `LEGACY_API_DEPARTMENT_ID is not configured`, `LEGACY_API_SCHEDULE_ID is not configured`.

### delete_employee

```json
{"action": "delete_employee", "cards": ["12345678901234", "..."]}
```

Har bir xodim avval u yozilgan terminallardan o‘chiriladi. Hammasidan o‘chirilgandagina bazadan ham o‘chiriladi.
Topilmagan kartalar jimgina o‘tkazib yuboriladi.

- Javob: `{"status": true, "alerts": ["Deleted successfully"]}`.
- Terminal xatosida: `{"status": false, "alerts": ["10.0.0.8 <xato>"]}`, xodim bazada qoladi.
- Eskisidan farqi: bitta xodimdagi xato boshqa xodimlarning o‘chirilishini to‘xtatmaydi.

### attendance

```json
{"action": "attendance", "cards": ["12345678901234"]}
```

`cards` ixtiyoriy, bo‘lmasa hamma xodim. Bugungi birinchi kirish (`hikvision.event_timezone` bo‘yicha bugun) va belgilar:

```json
{"status": true, "attendance": [{"name": "...", "card": "...", "note_type": "came_late", "note": "...",
  "dt": "2026-11-11 09:20:00", "before_9_15": false, "between_9_15_9_30": true, "after_9_30": false, "absent": false}]}
```

- Chegaralar eskidek qat’iy, ish jadvaliga qaramaydi:
  - `before_9_15`: 09:16:00 dan oldin;
  - `between_9_15_9_30`: 09:16:00–09:30:00;
  - `after_9_30`: undan keyin;
  - `absent`: bugun kirish yo‘q.
- Izoh (`note_type`: `came_late` / `left_early` / `absent`): kelgan bo‘lsa bugungi istalgan izoh, kelmagan bo‘lsa bugungi «kelmadi» izohi.
  Eski tizim kelmaganlarga boshqa kunlarning «kelmadi» izohini ham ko‘rsatib yuborardi, bu tuzatildi.

### add_note

```json
{"action": "add_note", "card": "12345678901234", "type": "came_late", "dt": "2026-11-11", "note": "Shifoxonada"}
```

- Javob: `{"status": true}`.
- Xatolar: `Invalid card number`, `Invalid note type`, `Invalid note text`, `Invalid date` (yangi).
- Bir xodim, kun va turga bitta izoh: qayta yuborilsa matni yangilanadi.

### get_reports

```json
{"action": "get_reports", "card": "", "ip": "", "type": "", "start": "2026-11-01 00:00:00", "end": "2026-11-30 23:59:59"}
```

`card`, `ip` (terminal IP’si) va `type` (`1` kirish, `2` chiqish) ixtiyoriy filtrlar, `start` va `end` majburiy.

```json
{"status": true, "reports": [{"id": "1520", "card": "...", "ip": "10.0.0.8", "type": "1", "dt": "2026-11-10 09:00:00"}]}
```

Eskidek `id` va `type` satr bo‘lib qaytadi, tartib vaqt bo‘yicha.

### get_work_time

```json
{"action": "get_work_time", "card": "", "start": "2026-11-01", "end": "2026-11-30"}
```

Har kun uchun oxirgi chiqish minus birinchi kirish. Tungi smenali jadvalda (kirish vaqti chiqishdan keyin) ish kuni 12 soat siljitib hisoblanadi.

```json
{"status": true, "work_time": [{"card": "...", "days": {"2026-11-01": "08:45:00", "2026-11-02": 0}, "total": "08:45:00"}]}
```

- Kirish yoki chiqishi yo‘q kun `0` (son).
- Chiqish kirishdan oldin bo‘lsa vaqt manfiy (`"-01:00:00"`) chiqadi va jamiga qo‘shilmaydi.
- Ro‘yxatda faqat shu oraliqda hodisasi bor xodimlar bo‘ladi.

## Umumiy farqlar

- Kutilmagan server xatosida `{"status": false, "alerts": ["Internal error"]}` qaytadi, tafsiloti `storage/logs` da.
  Eski tizim MySQL xato matnini qaytarardi.
- Bo‘lim va qurilma cheklovlari yo‘q: eski API kabi hamma xodim va terminalni ko‘radi. Shuning uchun IP filtri majburiy.
