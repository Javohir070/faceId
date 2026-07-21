---
name: Biocontrol
description: Laravel, Inertia React, Tailwind va Ant Design asosidagi biometric davomat va kirish nazorati boshqaruv tizimi
colors:
  brand-50: "#eefaf3"
  brand-100: "#d8f3e4"
  brand-600: "#228f5c"
  brand-700: "#1b734b"
  brand-950: "#0a2a1c"
  positive: "#228f5c"
  negative: "#dc2626"
  caution: "#ea580c"
  ink: "#1f2937"
  ink-muted: "#4b5563"
  ink-subtle: "#6b7280"
  surface: "#ffffff"
  surface-muted: "#f9fafb"
  canvas: "#f3f4f6"
  line: "#e5e7eb"
  line-strong: "#d1d5db"
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
    rounded: "{rounded.lg}"
  button-secondary:
    source: "antd:Button"
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  input:
    source: "antd:Input"
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
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

# Design System: Biocontrol

## Overview

**Creative North Star: “The Operations Console”**

Biocontrol — xodimlar, qurilmalar va jadvallar orqali biometric davomat hamda kirish nazoratini boshqarish uchun task-first admin panel. Amaldagi frontend **Laravel + Inertia React + Tailwind CSS + Ant Design 6** asosida qurilgan. Tailwind layout va responsive utilitylar uchun, Ant Design esa interaktiv komponentlar uchun ishlatiladi. UI kodining asosiy manbalari:

- tokenlar: `resources/css/app.css` (CSS variables) va `tailwind.config.js`;
- tema: `resources/js/Components/AdminThemeProvider.jsx` (light/dark);
- layoutlar: `resources/js/Layouts/AdminLayout.jsx` va `GuestLayout.jsx`;
- qayta ishlatiladigan qismlar: `resources/js/Components`;
- sahifalar: `resources/js/Pages`.

Generated `public/build` fayllari qo‘lda tahrirlanmaydi.

## Visual language

Interfeys xavfsizlik va HR operatorlari tomonidan uzoq vaqt ishlatiladi. Yashil brend biometric operatsiyalar va muvaffaqiyat holatlarini ifodalaydi; light va dark rejimlar `html[data-admin-theme]` orqali boshqariladi.

- **Brand:** `brand-600` primary CTA, aktiv nav, fokus va tanlangan holatlar uchun. Dark rejimda `brand-400`/`brand-600` yorqinroq variant ishlatiladi.
- **Canvas:** `canvas` sahifa foni; `surface` ish paneli; `surface-muted` toolbar, table header va hover.
- **Ink:** asosiy matn `ink`, yordamchi matn `ink-muted`; `ink-subtle` faqat metadata va ikkilamchi label uchun.
- **Semantic:** success `positive`, destructive `negative`, warning `caution`; rang doim matn yoki ikonka bilan birga keladi.
- **Theme:** default dark; foydalanuvchi header orqali light/dark almashtiradi. Ant Design tokenlari `AdminThemeProvider` orqali sinxronlanadi.

## Typography

Barcha product UI uchun bitta oila — **Nunito Sans**. Page title `text-xl sm:text-2xl font-extrabold`, section title `text-lg font-extrabold`, boshqaruv label va tugmalari `text-sm font-bold`. Fluid display typography ishlatilmaydi. Uzun izohlar 65–75ch bilan cheklanadi.

## Layout and elevation

- Desktopda collapsible sidebar, sticky header va to‘liq kenglikdagi content.
- `lg`dan pastda sidebar overlay sifatida ochiladi; tablelar horizontal scroll oladi.
- Data rows flat va `line` bilan ajratiladi. Nested cards ishlatilmaydi.
- `shadow-ambient` card/table/auth panel uchun, `shadow-floating` faqat dropdown va dialog uchun.
- Z-index tartibi: header 20 → overlay 30 → sidebar 40 → dialog 50.

## Component vocabulary

- **Buttons:** Ant Design `Button`; primary, default, text va danger variantlari.
- **Forms:** Ant Design `Input`, `InputNumber`, `Select`, `Upload`, `Checkbox`, `Switch`; `FormField`, `FormCard`, `InputError`.
- **Biometric:** `FaceCaptureField` — xodim yuz rasmini yuklash yoki kamera orqali olish.
- **CRUD:** `PageHeader`, `ResourceActions`, `FormActions`, `RoleBadge`, `EmptyState`, `Pagination`, `TableShell`, `ShowPage`.
- **Navigation:** Inertia `Link`; mutation va logout uchun `router`; form submit uchun `useForm`.
- **Icons:** faqat `@ant-design/icons`.
- **Feedback:** `FlashMessages`; delete tasdiqlash Ant Design `Modal.confirm` orqali.

## Interaction rules

Har bir interaktiv element default, hover, focus, active, disabled va processing holatiga ega bo‘lsin. Odatdagi transition 150–200ms va `ease-productive`. Motion faqat holatni tushuntiradi. `prefers-reduced-motion` global ravishda qo‘llab-quvvatlanadi.

Empty state foydalanuvchiga keyingi harakatni ko‘rsatadi; loading uchun Ant Design `loading` prop ishlatiladi.

## Do

- Mavjud token va umumiy komponentlarni qayta ishlating.
- Ichki linklarda Inertia oqimini saqlang.
- Admin va auth yuzalarida bir xil rang, radius, fokus va matn tizimini ishlating.
- Light va dark rejimlarda kontrast va hover holatlarini tekshiring.
- O‘zbekcha uzun label, mobile overflow va keyboard navigatsiyasini tekshiring.
- Matn kontrastini WCAG AA darajasida saqlang.

## Don’t

- Feather/Lucide ikonkalari yoki uchinchi tomon UI kutubxonalarini qo‘shmang.
- `public/build` ichidagi hashli assetlarni tahrirlamang.
- Gradient text, glassmorphism, nested card, dekorativ motion yoki tasodifiy katta z-index ishlatmang.
- Yangi sahifalarda inline style, yangi bir martalik rang yoki takroriy CRUD markup yaratmang.
- `ink-subtle`ni asosiy body matni sifatida ishlatmang.
- Theme tokenlarini faqat bitta joyda (CSS yoki JSX) qoldirmang — ikkalasi sinxron bo‘lishi kerak.
