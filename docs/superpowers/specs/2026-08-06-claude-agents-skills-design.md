# Claude Code agent va skill'lari

## Maqsad

Loyihaga uchta narsani beradigan agent va skill to'plami o'rnatish:

1. **Tizimni tushunish** — arxitektura, ruxsat tartibi, department scope, Hikvision oqimi
2. **Kam kod yozish** — CRUD boilerplate'ini avtomatlashtirish
3. **Chiroyli dizayn** — mavjud dizayn tizimiga mos UI

Hozir `.claude/` papkasi umuman yo'q. `.cursor/skills/impeccable/` da kuchli dizayn skill'i bor, lekin Claude Code uni ko'rmaydi.

## Tuzilma

```
.claude/
├── skills/
│   ├── biocontrol-resource/
│   │   ├── SKILL.md
│   │   ├── scripts/check-wiring.mjs
│   │   └── templates/
│   ├── biocontrol-design/
│   │   └── SKILL.md
│   └── impeccable/
│       └── SKILL.md          # ko'prik, nusxa emas
└── agents/
    ├── biocontrol-reviewer.md
    ├── hikvision-specialist.md
    └── biocontrol-expert.md
```

Hammasi git'ga commit qilinadi.

---

## Skill: `biocontrol-resource`

Yangi admin resursi qo'shishni avtomatlashtiradi.

### Ish taqsimoti

Ruxsat ulash tartibi hamma resursda bir xil, lekin CRUD qatlamlari farq qiladi (`Group` daraxt, `Employee` face upload, `Report` 8 xil tur). Shuning uchun:

| Kim | Nima |
|---|---|
| Skript | 6-nuqtani (ruxsat migratsiyasi) **yaratadi**; oltalasini ham **tekshiradi** |
| Claude | 1–5 nuqtadagi tahrirni + PHP/JSX qatlamlarni shablon bo'yicha yozadi |

Ya'ni ulanish nuqtasi jami oltita (quyida ro'yxati). Oltinchisi yangi fayl bo'lgani uchun skript uni to'liq yarata oladi; qolgan beshtasi mavjud fayl ichiga tahrir, ularni Claude qiladi.

Skript mavjud PHP/JS fayllarni **qayta yozmaydi** — regex bilan manba tahrirlash mo'rt va jimgina buzadi.

### `check-wiring.mjs`

Kirish: resurs kaliti (masalan `groups`).

Tekshiradigan 6 nuqta:

1. `config/menu_permissions.php` — `resources` massivida kalit bormi
2. `routes/web.php` — `Route::resource` + `permission:` middleware bormi
3. `app/Http/Middleware/HandleInertiaRequests.php` — `can` xaritasida bormi
4. `resources/js/config/adminNav.js` — menyu elementi bormi
5. `resources/js/routes.js` — URL builder bormi
6. `database/migrations/` — ruxsat migratsiyasi bormi

Chiqish: har nuqta uchun ✓ / ✗ va yetishmagani uchun aniq fayl yo'li.

**Xato holati:** fayl topilmasa yoki tuzilma kutilganidan farq qilsa, skript "hammasi joyida" demaydi — `exit 1` va aniq sabab beradi. Jim o'tib ketish eng xavfli holat.

`--generate-migration <kalit> <ruxsat-nomi>` bayrog'i bilan `2026_08_03_161000_add_groups_permission.php` nusxasidan yangi migratsiya yaratadi.

### Shablonlar

Manba: `Group` (daraxt tuzilishi) va `Note` (oddiy) CRUD'i — eng yangi va toza namunalar.

- `Controller` — konstruktor injection, `Inertia::render`, `redirect()->with('success')`
- `Service` — `DB::transaction`, repository chaqiruvi
- `Repository` + `Interface` — `filteredQuery()`, `paginate()`
- `StoreXRequest` / `UpdateXRequest` — department-scoped bo'lsa `MergesScopedDepartmentId`
- `Index.jsx` / `Form.jsx` / `Show.jsx` — `PageHeader`, `TableShell`, `FormCard`, `FormField`
- Feature test — `InteractsWithPermissions` bilan authorized/unauthorized

### SKILL.md tartibi

1. Resurs nomi, ruxsat nomi (o'zbekcha), department-scoped yoki yo'q — aniqlash
2. Migratsiyani skript bilan yaratish
3. Backend qatlamlarni shablon bo'yicha yozish
4. `AppServiceProvider::$repositories` ga bind qo'shish
5. 5 ta ulanish tahririni qilish
6. Inertia sahifalarni yozish
7. Testni yozish
8. `check-wiring.mjs` ni ishga tushirib o'zini tekshirish
9. `php artisan test` ni ishga tushirish

---

## Skill: `biocontrol-design`

Kundalik UI ishi uchun yengil skill.

### Mazmuni

- **Tokenlar:** `brand-600` CTA/aktiv holat; `canvas` sahifa foni, `surface` panel, `surface-muted` toolbar/hover; `ink` asosiy matn, `ink-muted` yordamchi; `positive`/`negative`/`caution` semantik
- **Ish taqsimoti:** joylashuv va responsive — Tailwind; interaktiv element — Ant Design 6
- **Mavjud komponentlar:** `PageHeader`, `TableShell`, `FormCard`, `FormField`, `InputError`, `ShowPage`, `Pagination`, `FlashMessages`, `StatusTag`, `FaceCaptureField`, `EmployeeDevicesPanel` — yangi sahifada shulardan foydalanish
- **Light/dark:** `AdminThemeProvider` va `resources/css/app.css` tokenlari doim sinxron; faqat bittasini o'zgartirish mumkin emas
- **Navigatsiya:** Inertia `Link` / `router` / `useForm`; ichki yo'nalishda `<a>` yoki `fetch` yo'q
- **Taqiqlar:** Feather/Lucide yo'q (faqat `@ant-design/icons`), nested card yo'q, gradient text/glassmorphism yo'q, inline style yo'q, tasodifiy z-index yo'q, `public/build` tahrirlanmaydi, `ink-subtle` asosiy matn sifatida ishlatilmaydi
- **O'zbekcha:** uzun label sig'ishi, mobil overflow, WCAG AA kontrast (matn ≥4.5:1)

Manba: `DESIGN.md`, `PRODUCT.md`, `.cursor/rules/impeccable-frontend.mdc`.

---

## Skill: `impeccable` (ko'prik)

`.claude/skills/impeccable/SKILL.md` faqat `.cursor/skills/impeccable/SKILL.md` ni o'qishga yo'naltiradi. Nusxa olinmaydi — ikkita nusxa vaqt o'tib bir-biridan uzoqlashadi va Cursor'da yangilanish Claude Code'ga yetib bormaydi.

### Ikkita dizayn skill'i kesishmasligi

`description` maydonlari ataylab ajratiladi:

| Skill | Ishga tushish holati |
|---|---|
| `biocontrol-design` | Yangi admin sahifa, forma, jadval; mavjud komponentdan foydalanish |
| `impeccable` | Redesign, audit, polish, vizual sifatni ko'tarish |

---

## Agent: `biocontrol-reviewer`

Tools: `Read`, `Grep`, `Glob`, `Bash` (faqat o'qiydi — hech narsani o'zgartira olmaydi).

Tekshiruv ro'yxati:

1. 6 ta ruxsat ulanish nuqtasi — `check-wiring.mjs` orqali
2. `resources/js/routes.js` `routes/web.php` bilan sinxronmi (Ziggy yo'q, qo'lda yuritiladi — xato faqat ishlash paytida chiqadi)
3. `department_id` li yangi model `BelongsToDepartmentScope` oldimi; `withoutGlobalScopes()` o'rinli ishlatilganmi
4. Controller to'g'ridan-to'g'ri Eloquent'ga yozyaptimi (qatlam buzilishi)
5. Yangi repository `AppServiceProvider::$repositories` da ro'yxatdanmi
6. Foydalanuvchiga ko'rinadigan matnlar o'zbekchami
7. Feature test yozilganmi
8. Frontend taqiqlari buzilganmi (inline style, Lucide, nested card, `public/build`)

## Agent: `hikvision-specialist`

Tools: to'liq.

Biladigan invariantlar:

- Qurilma login/paroli `devices` jadvalida, `.env` da emas
- Xodim yaratish/tahrirlash avtomatik qurilmaga ketmaydi — faqat Sinxronizatsiya orqali, queue bilan
- Uchta event kanali (webhook, alertStream, scheduled pull) `ReportService` da birlashadi va dublikat o'sha yerda tozalanadi
- `IsapiClient` HTTP Digest auth ishlatadi
- `devices:stream-events` scheduler orqali emas, alohida terminalda
- Joblar `app/Jobs/Hikvision/` da

Manba: `docs/hikvision-integration.md`.

## Agent: `biocontrol-expert`

Tools: `Read`, `Grep`, `Glob` (faqat o'qiydi).

Arxitektura savollariga javob beradi, kerakli faylni topadi. `CLAUDE.md` ni asos qilib oladi.

---

## Tekshirish rejasi

1. **`check-wiring.mjs`** — `groups`, `notes`, `employees` uchtasida ham "to'liq" chiqishi kerak. Keyin ataylab bitta ulanishni olib tashlab, skript topishini tekshirish. Buzilgan/o'zgargan faylda jim o'tmasligini alohida tekshirish.
2. **`biocontrol-reviewer`** — hozirgi 11 ta o'zgargan faylga qo'yib ko'rish; bilgan muammolarni topmasa, ro'yxatni kuchaytirish.
3. **`biocontrol-resource`** — sinov resursi (`Tag`) to'liq yaratib ko'rish, `php artisan test` o'tishini tekshirish, keyin o'chirish.
4. **Dizayn skill'lari** — "yangi jadval qo'sh" → `biocontrol-design`, "chiroyliroq qil" → `impeccable`. Ikkalasi bir vaqtda chiqsa `description` tuzatish.

## Qamrovdan tashqari

- `report-specialist` agenti (hozircha kerak emas deb qaror qilindi)
- `.cursor/skills/impeccable` ni o'zgartirish yoki yangilash
- Mavjud CRUD'larni yangi shablonga ko'chirish
- CI/hook orqali `check-wiring.mjs` ni avtomatik ishga tushirish
