# Product

## Register

product

## Users

- **Tizim administratorlari** — foydalanuvchilar, rollar, ruxsatlar va umumiy sozlamalarni boshqaradi.
- **HR / kadrlar operatorlari** — xodimlar, bo‘limlar, jadvallar va yuz ma’lumotlarini kiritadi va yangilaydi.
- **Xavfsizlik operatorlari** — qurilmalar, kategoriyalar va kirish hodisalarini kuzatadi.
- Kontekst: ofis yoki nazorat xonasida, uzoq vaqt ochiq qoladigan ish paneli. Vazifa — xodim va qurilma ma’lumotlarini tez kiritish, holatni kuzatish, biometric integratsiyani boshqarish.

## Product Purpose

**Biocontrol** — biometric davomat va kirish nazorati uchun admin panel. Hikvision qurilmalari bilan integratsiya, xodimlar yuz ma’lumotlari, ish jadvallari, bo‘limlar va ruxsatlar orqali tashkilot darajasida kirish nazoratini ta’minlaydi.

Ko‘rsatiladigan nom `APP_NAME` (hozirgi default: EIdora) orqali sozlanadi; kod va hujjatlarda tizim nomi **Biocontrol**.

Muvaffaqiyat ko‘rsatkichi: operator xodim yoki qurilmani tez qo‘shadi, holat aniq ko‘rinadi, navigatsiya modullar bo‘yicha tushunarli.

## Brand Personality

**Ishonchli · Aniq · Operatsion**

Xavfsizlik va kadrlar jarayonlariga xizmat qiladi. Dizayn bezak emas — tez va xatosiz operatsion vazifalar uchun vosita. Yashil brend muvaffaqiyat va faol holatni, qorong‘u rejim esa uzoq monitoring sessiyalarini qo‘llab-quvvatlaydi.

## Anti-references

- Generic AI SaaS admin (purple gradient, neon glow, glassmorphism).
- CMS kontent muharriri yoki ko‘p tilli matn tablari (bu tizim operatsion CRUD uchun).
- Tailwind layoutlari bilan moslashtirilmagan uchinchi tomon komponentlari.
- Nested card layout, 999 z-index, bounce animatsiyalar.
- Past kontrastli kulrang matn (`ink-subtle` ustida o‘qish qiyin).
- Faqat light yoki faqat dark dizayn — ikkala rejim ham bir xil sifatda qo‘llab-quvvatlanishi kerak.

## Design Principles

1. **Vazifa birinchi** — har ekran bir asosiy harakatga yo‘naltirilgan bo‘lsin (qo‘shish, tahrirlash, ko‘rish, biometrik ma’lumot yuklash).
2. **Bir xil tizim** — layout uchun Tailwind, interaktiv komponentlar uchun Ant Design; light/dark bir xil tokenlar bilan.
3. **O‘qilishi oson** — jadval, forma va holat ko‘rsatkichlari aniq kontrast va ierarxiyaga ega.
4. **O‘zbek tiliga mos** — matn uzunligi va label o‘lchamlari o‘zbekcha interfeys uchun qulay.
5. **Kengaytiriladigan** — yangi modullar (qurilmalar, xodimlar, jadvallar) mavjud komponentlar va tokenlar orqali qo‘shiladi.

## Accessibility & Inclusion

- WCAG 2.1 AA maqsad (matn kontrasti ≥4.5:1, katta matn ≥3:1).
- `prefers-reduced-motion` qo‘llab-quvvatlash.
- Klaviatura navigatsiyasi va fokus holatlari aniq ko‘rinishi kerak.
- Forma xatolari matn va rang bilan birga ko‘rsatiladi.
- Dark va light rejimlarda bir xil o‘qilishi va fokus ko‘rinishi saqlanadi.
