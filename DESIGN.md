---
name: olim.uz CMS
description: Laravel, Inertia React, Tailwind va Ant Design asosidagi institut kontent boshqaruv tizimi
colors:
  brand-50: "#eef3ff"
  brand-100: "#dce7ff"
  brand-600: "#3874ff"
  brand-700: "#2d5ecc"
  brand-950: "#182951"
  positive: "#25b003"
  negative: "#dc2f18"
  caution: "#c96808"
  ink: "#31374a"
  ink-muted: "#596579"
  ink-subtle: "#6e7891"
  surface: "#ffffff"
  surface-muted: "#eff2f6"
  canvas: "#f5f7fa"
  line: "#e3e6ed"
  line-strong: "#cbd0dd"
typography:
  page-title:
    fontFamily: '"Nunito Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "1.5rem"
    fontWeight: 800
    lineHeight: 1.25
  section-title:
    fontFamily: '"Nunito Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "1.125rem"
    fontWeight: 800
    lineHeight: 1.35
  body:
    fontFamily: '"Nunito Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: '"Nunito Sans", ui-sans-serif, system-ui, sans-serif'
    fontSize: "0.875rem"
    fontWeight: 700
    lineHeight: 1.25
rounded:
  md: "0.375rem"
  lg: "0.5rem"
  full: "9999px"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "2rem"
components:
  button-primary:
    source: "antd:Button[type=primary]"
    backgroundColor: "{colors.brand-600}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
  button-secondary:
    source: "antd:Button"
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
  input:
    source: "antd:Input"
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
  card:
    source: "resources/css/app.css:.card"
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  table-shell:
    source: "resources/css/app.css:.table-shell"
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
---

# Design System: olim.uz CMS

## Overview

**Creative North Star: “The Institutional Workbench”**

olim.uz — institut ma’lumotlarini boshqarish uchun task-first CMS. Amaldagi frontend **Laravel + Inertia React + Tailwind CSS + Ant Design 6** asosida qurilgan. Tailwind layout va responsive utilitylar uchun, Ant Design esa interaktiv komponentlar uchun ishlatiladi. UI kodining asosiy manbalari:

- tokenlar: `tailwind.config.js`;
- umumiy CSS komponentlar: `resources/css/app.css`;
- layoutlar: `resources/js/Layouts/AdminLayout.jsx` va `GuestLayout.jsx`;
- qayta ishlatiladigan qismlar: `resources/js/Components`;
- sahifalar: `resources/js/Pages`.

Eski Phoenix/Bootstrap, Feather, Lucide va Summernote tizimi olib tashlangan. Generated `public/build` fayllari qo‘lda tahrirlanmaydi.

## Visual language

Interfeys kunduzgi ofis muhitida tez-tez ishlatiladi. Shu sabab light canvas, oq task surfaces, yuqori kontrastli slate matn va cheklangan institutsional ko‘k tanlangan.

- **Brand:** `brand-600` faqat primary CTA, aktiv nav, fokus va tanlangan holatlar uchun.
- **Canvas:** `canvas` sahifa foni; `surface` ish paneli; `surface-muted` toolbar, table header va hover.
- **Ink:** asosiy matn `ink`, yordamchi matn `ink-muted`; `ink-subtle` faqat metadata va ikkilamchi label uchun.
- **Semantic:** success `positive`, destructive `negative`, warning `caution`; rang doim matn yoki ikonka bilan birga keladi.

## Typography

Barcha product UI uchun bitta oila — **Nunito Sans**. Page title `text-2xl font-extrabold`, section title `text-lg font-extrabold`, boshqaruv label va tugmalari `text-sm font-bold`. Fluid display typography ishlatilmaydi. Uzun izohlar 65–75ch bilan cheklanadi.

## Layout and elevation

- Desktopda 288px sidebar, 72px sticky header va markazda `max-w-[1440px]` content.
- `lg`dan pastda sidebar overlay sifatida ochiladi; tablelar horizontal scroll oladi.
- Data rows flat va `line` bilan ajratiladi. Nested cards ishlatilmaydi.
- `shadow-ambient` card/table/auth panel uchun, `shadow-floating` faqat dropdown va dialog uchun.
- Z-index tartibi: header 20 → overlay 30 → sidebar 40 → dialog 50.

## Component vocabulary

- **Buttons:** Ant Design `Button`; primary, default, text va danger variantlari.
- **Forms:** Ant Design `Input`, `InputNumber`, `Select`, `Upload`, `Checkbox`, `Switch`; `InputError`, `LangTabs`, `RichTextEditor`.
- **CRUD:** `PageHeader`, `ResourceActions`, `FormActions`, `StatusBadge`, `EmptyState`, `Pagination`.
- **Navigation:** Inertia `Link`; mutation va logout uchun `router`; form submit uchun `useForm`.
- **Icons:** faqat `@ant-design/icons`.
- **Rich text:** Tiptap; toolbar va editor `RichTextEditor` orqali.

## Interaction rules

Har bir interaktiv element default, hover, focus, active, disabled va processing holatiga ega bo‘lsin. Odatdagi transition 150–200ms va `ease-productive`. Motion faqat holatni tushuntiradi. `prefers-reduced-motion` global ravishda qo‘llab-quvvatlanadi.

Delete kabi xavfli amallar native `<dialog>` orqali tasdiqlanadi. Empty state foydalanuvchiga keyingi harakatni ko‘rsatadi; loading uchun skeleton ishlatiladi.

## Do

- Mavjud token va umumiy komponentlarni qayta ishlating.
- Ichki linklarda Inertia oqimini saqlang.
- Admin va auth yuzalarida bir xil rang, radius, fokus va matn tizimini ishlating.
- O‘zbekcha uzun label, UZ/RU/EN tab xatolari, mobile overflow va keyboard navigatsiyasini tekshiring.
- Matn kontrastini WCAG AA darajasida saqlang.

## Don’t

- Phoenix/Bootstrap classlari, Feather/Lucide ikonkalari yoki Summernote qo‘shmang.
- `public/build` ichidagi hashli assetlarni tahrirlamang.
- Gradient text, glassmorphism, nested card, dekorativ motion yoki tasodifiy katta z-index ishlatmang.
- Yangi sahifalarda inline style, yangi bir martalik rang yoki takroriy CRUD markup yaratmang.
- `ink-subtle`ni asosiy body matni sifatida ishlatmang.
