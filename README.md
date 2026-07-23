# Biocontrol

Biometric davomat va kirish nazorati uchun Laravel admin panel. Hikvision qurilmalari bilan integratsiya, xodimlar yuz ma’lumotlari, ish jadvallari va ruxsatlar boshqaruvi.

Ko‘rsatiladigan ilova nomi `.env` dagi `APP_NAME` orqali sozlanadi (default: **EIdora**).

## Stack

| Qatlam | Texnologiya |
|--------|-------------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | Inertia.js + React 19 (JSX) |
| UI | Tailwind CSS 3 + Ant Design 6 |
| Ruxsatlar | Spatie Laravel Permission |
| Biometric | Hikvision ISAPI + webhook (`/api/webhooks/hikvision/events`) |

## Hikvision integratsiya

Batafsil qo'llanma: [`docs/hikvision-integration.md`](docs/hikvision-integration.md)

- Admin → **Qurilmalar** — ISAPI ulanish, webhook, eshik ochish, sync
- Admin → **Xodimlar** — faqat bazaga saqlanadi; qurilmaga **avtomatik** ketmaydi
- Admin → **Sinxronizatsiya** — xodimlarni tanlab qurilmalarga qo‘lda yuborish (queue orqali)
- Sync va qurilma amallari `QUEUE_CONNECTION` (odatda `database`) orqali fon ishiga tushadi — `php artisan queue:work` yoki `composer dev` ishlatilsin
- `.env` da `HIKVISION_WEBHOOK_SECRET` va `APP_URL` ni sozlang
- Har bir qurilmaning IP, login, parol ma'lumotlari **Admin → Qurilmalar** da saqlanadi (`.env` emas)

## Modullar

- **Qurilmalar** — kategoriyalar va access controller qurilmalar
- **Tashkilot** — bo‘limlar, ish jadvallari, xodimlar (yuz rasmi bilan)
- **Sozlamalar** — foydalanuvchilar, rollar, ruxsatlar

## Dizayn tizimi

- `DESIGN.md` — rang tokenlari, tipografiya, komponent qoidalari
- `PRODUCT.md` — product register (Impeccable skill uchun)
- `.impeccable/` — avtomatik dizayn detektor konfiguratsiyasi
- `.cursor/rules/impeccable-frontend.mdc` — frontend qoidalari

Light/dark tema `AdminThemeProvider` orqali boshqariladi; CSS tokenlar `resources/css/app.css` da.

## O‘rnatish

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
```

```bash
php artisan serve
```

## Ishlab chiqish

```bash
# Backend + frontend parallel
composer dev

# Yoki alohida
php artisan serve
npm run dev
```

## Testlar

```bash
php artisan test
```

## Hujjatlar

- [Laravel](https://laravel.com/docs)
- [Inertia.js](https://inertiajs.com)
- [Ant Design](https://ant.design)

## Litsenziya

MIT
