# Hikvision Face ID — Laravel integratsiya qo'llanmasi

Bu hujjat BioControl tizimida Hikvision Face ID qurilmalari bilan ISAPI orqali to'liq integratsiyani tavsiflaydi. Asosiy manba — Spring Boot amaliy qo'llanma; Laravel/PHP uchun moslashtirilgan.

## ISAPI nima?

ISAPI — Hikvision qurilmalari bilan HTTP orqali muloqot qilish uchun standart API. BioControl quyidagi operatsiyalarni bajaradi:

| Operatsiya | Endpoint | HTTP |
|----------|----------|------|
| Foydalanuvchi qo'shish | `/ISAPI/AccessControl/UserInfo/Record?format=json` | POST |
| Foydalanuvchi yangilash | `/ISAPI/AccessControl/UserInfo/Modify?format=json` | PUT |
| Foydalanuvchi o'chirish | `/ISAPI/AccessControl/UserInfo/Delete?format=json` | PUT |
| Yuz yuklash | `/ISAPI/Intelligent/FDLib/FDSetUp?format=json` | PUT (multipart) |
| Yuz o'chirish | `/ISAPI/Intelligent/FDLib/FDDelete?format=json` | PUT |
| Qurilma ma'lumoti | `/ISAPI/System/deviceInfo?format=json` | GET |
| Webhook sozlash | `/ISAPI/Event/notification/httpHosts/1` | PUT |
| Eshik ochish | `/ISAPI/AccessControl/RemoteControl/door/{doorNo}` | PUT |
| Qayta yuklash | `/ISAPI/System/reboot` | PUT |

## Autentifikatsiya

Hikvision **HTTP Digest Authentication** ishlatadi (oddiy Basic emas). Laravel `Http` klienti Guzzle orqali digest auth qo'llab-quvvatlaydi:

```php
Http::withOptions([
    'auth' => [$username, $password, 'digest'],
])->get($url);
```

Implementatsiya: `app/Services/Hikvision/IsapiClient.php`

## BioControl arxitekturasi

```
Admin panel (Qurilmalar / Xodimlar)
        │
        ▼
EmployeeSyncService / HikvisionDeviceService
        │
        ▼
IsapiClient (HTTP Digest)
        │
        ▼
Hikvision qurilma (ISAPI)
        │
        ▼ (webhook POST)
/api/webhooks/hikvision/events  →  AccessControllerEvent
```

## Konfiguratsiya

### `.env` (faqat server sozlamalari)

```env
HIKVISION_WEBHOOK_SECRET=your-secret-here
HIKVISION_WEBHOOK_TRUST_DEVICE_IP=true
APP_URL=http://your-server.example
HIKVISION_TIMEOUT=10
```

### Admin → Qurilmalar (`devices` jadvali)

Har bir Face ID qurilma uchun alohida saqlanadi — `.env` da emas:

| Maydon | Tavsif |
|--------|--------|
| `ip_address` | Qurilma IP |
| `port` | Port (odatda **80** yoki **443**, 8000 emas) |
| `use_https` | HTTPS yoqilganmi |
| `user` | ISAPI login |
| `password` | ISAPI parol (shifrlangan) |
| `type` | Kirish (0) / Chiqish (1) |
| `is_online` | Ulanish holati |

Bir nechta qurilma qo'shishingiz mumkin — har biri o'z IP/login/parol bilan ishlaydi.

## Xodim ↔ qurilma sinxronizatsiyasi

| BioControl maydoni | Hikvision maydoni |
|--------------------|-------------------|
| `employees.card` | `employeeNo` |
| `employees.name` | `name` |
| `valid_from_dt` | `Valid.beginTime` |
| `valid_to_dt` | `Valid.endTime` |
| `faces.face` (base64) | multipart `img` |

Xodim yaratilganda / yangilanganda / o'chirilganda `EmployeeSyncService` barcha **onlayn** qurilmalarga sync yuboradi.

## Qurilma amallari (admin jadvali)

| Amal | Route | Tavsif |
|------|-------|--------|
| Ulanish | `POST /admin/devices/{id}/connect` | Ping + webhook + onlayn holat |
| Uzish | `POST /admin/devices/{id}/disconnect` | Onlayn holatni o'chirish |
| Ochish | `POST /admin/devices/{id}/open` | Eshikni masofadan ochish |
| Qayta yuklash | `POST /admin/devices/{id}/restart` | Qurilmani reboot |
| Tozalash | `POST /admin/devices/{id}/clear` | Barcha xodimlarni qurilmadan o'chirish |
| Sync | `POST /admin/devices/{id}/sync-employees` | Barcha xodimlarni qayta yuborish |

## Webhook

Qurilma ulanish paytida avtomatik webhook sozlanadi:

- URL: `{APP_URL}/api/webhooks/hikvision/events`
- Secret: `HIKVISION_WEBHOOK_SECRET` (header, query yoki path orqali)

Qabul qiluvchi: `app/Http/Controllers/WebhookController.php`

## Qurilma ping (fon monitoring)

Har `HIKVISION_PING_INTERVAL` daqiqada barcha qurilmalarga ISAPI ping yuboriladi:

```bash
php artisan devices:ping
```

Ishlatish uchun scheduler yoqilgan bo‘lishi kerak:

```bash
# development
php artisan schedule:work

# production cron
* * * * * php /path/to/artisan schedule:run
```

`.env`:

```env
HIKVISION_PING_ENABLED=true
HIKVISION_PING_INTERVAL=1
```

Jadvalda **Ping: 2026-07-21 16:30** — oxirgi tekshiruv vaqti.

## Yuz rasmi optimizatsiyasi

Hikvision odatda **512 KB** dan katta rasmlarni rad etadi. `FaceImageOptimizer` avtomatik resize va JPEG siqish qiladi.

## Xatoliklarni hal qilish

| Muammo | Yechim |
|--------|--------|
| 401 Unauthorized | Digest auth, login/parolni tekshiring |
| employeeNoAlreadyExist | Avtomatik Modify rejimiga o'tiladi |
| Face upload 400 | Rasm hajmi/formati; optimizer ishlayaptimi? |
| Port yoki protokol noto‘g‘ri | Port **80** (HTTP) yoki **443** (HTTPS) ni sinab ko‘ring; 8000 ko‘pincha ISAPI emas |
| Connection was reset (cURL 56) | HTTP o‘rniga HTTPS yoqing yoki portni 80/443 ga o‘zgartiring |
| Holat yangilanmayapti | `php artisan schedule:work` yoki cron ishlayotganini tekshiring |
| Webhook kelmayapti | `HIKVISION_WEBHOOK_SECRET`, firewall, APP_URL |

## Kod joylashuvi

```
app/Services/Hikvision/
  IsapiClient.php           — HTTP Digest klient
  HikvisionUserService.php  — User CRUD + face
  HikvisionDeviceService.php — ulanish, webhook, reboot, eshik
  FaceImageOptimizer.php    — rasm siqish
  EmployeeSyncService.php   — xodim ↔ qurilma sync

app/Http/Controllers/
  DeviceActionController.php
  BiocontrolController.php  — global diagnostika
  WebhookController.php     — event qabul (mavjud)

config/hikvision.php
docs/hikvision-integration.md  — ushbu fayl
```

## Best practices

1. Har bir HTTP chaqiruvda timeout (`connect_timeout`, `timeout`)
2. Barcha ISAPI javoblarini log qilish (`storage/logs/laravel.log`)
3. Ko'p qurilma uchun sync ni queue ga ko'chirish (keyingi bosqich)
4. Har bir qurilma modeli uchun rasmiy Hikvision API hujjatini tekshirish

## Eslatma

Qurilma modellari biroz farq qilishi mumkin. Endpoint yoki payload mos kelmasa, Hikvision Developer Portal dan o'z modelingiz hujjatini oling.
