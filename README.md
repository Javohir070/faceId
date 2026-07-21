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
| Biometric | Hikvision webhook (`/api/webhooks/hikvision/events`) |

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
