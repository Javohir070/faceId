# Telegram bot — login, qurilma oflayn va xodim muddati xabarnomalari

## Maqsad

Mavjud admin userlar Telegram bot orqali akkauntini bog‘lab, tizimdan ikki turdagi ogohlantirish olsin:

1. Qurilma pingdan o‘tmay, `is_online` false bo‘lganda — “qurilma ishlamayapti”.
2. Employee `valid_to_dt` yaqinlashganda (3 kun qolganda) va muddat tugaganda.

## Qamrov

- Bot orqali **mavjud** userni bog‘lash (email + password). Yangi user yaratilmaydi.
- `users.telegram_chat_id` saqlanadi.
- Xabarnomalar `telegram_chat_id` bo‘lgan **barcha** userlarga yuboriladi.
- Qurilma oflayn: faqat `is_online` true → false o‘tishida bir marta.
- Employee: 3 kun oldin bir marta + muddat tugaganda bir marta.
- Telegram update qabul qilish: **webhook** (`POST /api/telegram/webhook`).
- Env: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` (+ webhook secret).

## Yondashuv

Oddiy Laravel servis + webhook (tashqi bot SDK yo‘q). HTTP orqali Telegram Bot API.

## Arxitektura

### Konfiguratsiya

- `config/telegram.php`:
  - `token` ← `TELEGRAM_BOT_TOKEN`
  - `username` ← `TELEGRAM_BOT_USERNAME`
  - `webhook_secret` ← `TELEGRAM_WEBHOOK_SECRET` (Telegram `secret_token`)
  - `employee_expiry_warn_days` ← default `3`
- `.env.example` ga shu kalitlar qo‘shiladi.

### Ma’lumotlar bazasi

- Migration: `users` jadvaliga `telegram_chat_id` (string, nullable, unique).
- Migration: `employees` jadvaliga:
  - `expiry_warned_at` (nullable timestamp) — 3 kunlik ogohlantirish yuborilgan vaqt
  - `expiry_notified_at` (nullable timestamp) — muddat tugagan xabar yuborilgan vaqt
- `User` model: `telegram_chat_id` fillable/cast; bog‘langanligini tekshiruv helper ixtiyoriy.

### Servislar

- `App\Services\Telegram\TelegramBotService`
  - `sendMessage(chatId, text)`
  - `setWebhook(url, secretToken)` (artisan buyruq uchun)
  - Http client orqali `api.telegram.org/bot{token}/...`
- `App\Services\Telegram\TelegramNotifier`
  - `notifyAllLinkedUsers(string $text): void` — `User::whereNotNull('telegram_chat_id')` bo‘yicha yuboradi
  - Yuborish xatolari loglanadi, bitta chat xatosi boshqalarni to‘xtatmaydi
- `App\Services\Telegram\TelegramAuthHandler`
  - Conversation state: Cache key `telegram:auth:{chat_id}` → `{step, email?}`, TTL ~15 daqiqa
  - Qadamlar: `awaiting_email` → `awaiting_password` → bog‘lash yoki xato

### HTTP

- Route: `POST /api/telegram/webhook` (CSRF/sanctum yo‘q; secret token middleware)
- Middleware: `VerifyTelegramWebhookSecret` — header `X-Telegram-Bot-Api-Secret-Token` ni `config('telegram.webhook_secret')` bilan solishtiradi
- `TelegramWebhookController`: update’dan `message` ajratadi, handler’ga beradi, 200 qaytaradi

### Bot login oqimi

1. `/start` — agar chat allaqachon bog‘langan bo‘lsa: “Ulangan: {email}. Chiqish: /unlink”. Aks holda: “Email kiriting”.
2. Email matn — validatsiya, state `awaiting_password`, “Parol kiriting”.
3. Parol matn — `User` email bo‘yicha topiladi, `Hash::check`; muvaffaqiyatda:
   - shu `telegram_chat_id` boshqa userda bo‘lsa — eski userdan olib tashlanadi
   - joriy userga `telegram_chat_id` yoziladi
   - “Muvaffaqiyatli ulandingiz”
4. Noto‘g‘ri login — “Email yoki parol noto‘g‘ri”, state tozalanadi.
5. `/unlink` — joriy chat_id olib tashlanadi.

Parol hech qachon logga yozilmaydi.

### Qurilma oflayn xabarnoma

- Joylashuv: `HikvisionDeviceService` ping muvaffaqiyatsiz bo‘lib, `failures >= threshold` va oldingi `is_online === true` bo‘lganda (yangi saqlashdan oldin).
- Shunda `TelegramNotifier` chaqiriladi: qurilma nomi, IP, tur (kirish/chiqish), “Qurilma ishlamayapti / oflayn”.
- Faqat holat o‘tishida; keyingi oflayn pinglar qayta xabar yubormaydi.
- Qurilma qayta online bo‘lganda alohida “online” xabar **hozircha yo‘q** (YAGNI).

### Employee muddat xabarnoma

- Command: `employees:notify-expiry`
- Schedule: kuniga bir marta (masalan `08:00`), `routes/console.php`
- Logika:
  - **Warn:** `valid_to_dt` endi va `now()->addDays(warn_days)` oralig‘ida, `expiry_warned_at` null → xabar + `expiry_warned_at = now()`
  - **Expired:** `valid_to_dt < now()`, `expiry_notified_at` null → xabar + `expiry_notified_at = now()`
- Xabar matni: xodim ismi, karta, `valid_to_dt`
- `valid_to_dt` keyinroq uzaytirilsa: yangi muddat uchun flaglar tozalanishi kerak — Employee update/save da agar `valid_to_dt` o‘zgarsa va yangi qiymat kelajakda bo‘lsa, `expiry_warned_at` va `expiry_notified_at` null qilinadi (model observer yoki repository/update joyida).

### Artisan yordamchi

- `telegram:set-webhook {url?}` — `APP_URL` + `/api/telegram/webhook` ga webhook o‘rnatadi, secret token bilan.

## Xatoliklarni boshqarish

- Token bo‘sh: notifier no-op + warning log (app ishlashda qoladi).
- Telegram API xatosi: log, exception tashlamaslik (ping/schedule buzilmasin).
- Noma’lum webhook update: 200 OK, e’tiborsiz.
- Secret noto‘g‘ri: 403.

## Test

- Feature: webhook login success → `telegram_chat_id` saqlanadi.
- Feature: noto‘g‘ri parol → chat_id yozilmaydi.
- Feature: `/unlink` → chat_id null.
- Unit/feature: device ping oflayn o‘tishida notifier chaqiriladi; allaqachon oflayn qurilmada chaqirilmaydi (`Http::fake` / mock).
- Feature/command: `employees:notify-expiry` — 3 kun ichida warn bir marta; expired bir marta; takroriy ishga tushirishda ikkinchi marta yubormaydi.
- Middleware: secret yo‘q/noto‘g‘ri → 403.

## Chegaralar (YAGNI)

- Yangi user registratsiyasi bot orqali yo‘q.
- Long polling yo‘q.
- Online qaytgani haqida xabar yo‘q.
- Inline keyboard / keng menyu yo‘q — faqat login + unlink + xabarnomalar.
- Tashqi Telegram SDK paketlar qo‘shilmaydi.
