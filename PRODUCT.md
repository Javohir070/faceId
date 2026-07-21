# Product

## Register

product

## Users

- **Admin xodimlar** — institut kontentini boshqaradigan operatorlar (galereya, institut tarixi, foydalanuvchilar, rollar, ruxsatlar).
- **Texnik moderatorlar** — tizim sozlamalari va foydalanuvchi huquqlarini boshqaradi.
- Kontekst: ofis yoki ish joyida, kunduzgi yorug'lik, tez-tez qayta-qayta kiriladigan ish paneli. Vazifa — ma'lumot kiritish, tahrirlash, holatni kuzatish.

## Product Purpose

**olim.uz** — institut veb-sayti uchun kontent boshqaruv tizimi (CMS/admin panel). Galereya, institut tarixi va foydalanuvchi boshqaruvi orqali sayt kontentini yangilash, rollar va ruxsatlar bilan xavfsiz kirishni ta'minlash.

Muvaffaqiyat ko'rsatkichi: admin tez va xatosiz kontent kiritadi, holat aniq ko'rinadi, navigatsiya tushunarli.

## Brand Personality

**Ishonchli · Aniq · Xizmatkor**

Institutning rasmiy, lekin zamonaviy yuzini aks ettiradi. Admin panel dizayni kontentdan ko'ra vazifani bajarishga xizmat qiladi — bezak emas, vosita.

## Anti-references

- Generic AI SaaS admin (purple gradient, neon glow, glassmorphism).
- Phoenix/Bootstrap yoki boshqa eski admin shablonlarini qayta kiritish.
- Tailwind layoutlari bilan moslashtirilmagan uchinchi tomon komponentlari.
- Nested card layout, 999 z-index, bounce animatsiyalar.
- Past kontrastli kulrang matn (`text-body-tertiary` ustida o'qish qiyin).

## Design Principles

1. **Vazifa birinchi** — har ekran bir asosiy harakatga yo'naltirilgan bo'lsin (qo'shish, tahrirlash, ko'rish).
2. **Bir xil tizim** — layout uchun Tailwind, interaktiv komponentlar uchun Ant Design ishlatilib, admin va auth bitta vizual tilga ega bo'lsin.
3. **O'qilishi oson** — jadval, forma va holat ko'rsatkichlari aniq kontrast va ierarxiyaga ega.
4. **O'zbek tiliga mos** — matn uzunligi va label o'lchamlari o'zbekcha kontent uchun qulay.
5. **Kengaytiriladigan** — yangi modullar mavjud komponentlar va tokenlar orqali qo'shiladi.

## Accessibility & Inclusion

- WCAG 2.1 AA maqsad (matn kontrasti ≥4.5:1, katta matn ≥3:1).
- `prefers-reduced-motion` qo'llab-quvvatlash.
- Klaviatura navigatsiyasi va fokus holatlari aniq ko'rinishi kerak.
- Forma xatolari matn va rang bilan birga ko'rsatiladi.
