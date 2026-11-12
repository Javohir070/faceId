# Tarkibiy bo‘linmalar: bo‘lim bo‘yicha filtr

Foydalanuvchi so‘zlari: "Tarkibiy bo'linmalar filter ham qo'yish kerak bo'limlar bo'yicha".

Asosiy sahifa: [2026-11-12-structural-units-design.md](2026-11-12-structural-units-design.md).

## Nima o‘zgaradi

- Holat filtrlari (Hammasi / Binoda / Binoda emas / Sabablilar / Sababsiz) yonida **"Bo‘lim"** tanlovi (Ant Design
  `Select`, tozalash mumkin). Bo‘sh — hamma ko‘rinadigan bo‘limlar, avvalgidek.
- Bo‘lim tanlansa faqat shu bo‘limning guruh kartochkalari, "Guruhsiz" kartochkasi va xodimlari qoladi.
  **Filtr tugmalaridagi sonlar ham shu bo‘lim bo‘yicha** hisoblanadi.
- Bitta bo‘lim tanlanadi (Davomat sahifasidagidek). Bir nechta bo‘lim tanlash so‘ralmagan.

## Qarorlar

- **Filtr serverda.** Query parametr `?department_id=5`; controllerdagi `filterRules()` uni `nullable|integer` deb
  tekshiradi (Davomatdagidek `exists` yo‘q — topilmagan id bo‘sh sahifa beradi). Servis `build()` ga uchinchi
  argument `?int $departmentId` qo‘shiladi va xodimlar so‘rovi shu bo‘lim bilan cheklanadi; sanoqlar filtrdan
  keyin hisoblanadi. Shunday qilib klientga boshqa bo‘lim ma’lumoti umuman yuborilmaydi.
- **Yangilanishda saqlanadi.** Tanlov URL'da turadi; 30 soniyalik `useInertiaPoll(['board'])` `router.reload()`
  bilan joriy URL'ni qayta so‘raydi, shuning uchun tanlangan bo‘lim yo‘qolmaydi. Sahifani yangilash yoki havolani
  ulashish ham shu bo‘limni ochadi.
- **Bo‘limga cheklangan foydalanuvchida tanlov yashiriladi** (`departmentLocked = User::isDepartmentScoped()`).
  Unda bitta bo‘lim bor, kartochka yorliqlarida uning nomi baribir ko‘rinadi — bloklangan tanlov joy egallaydi,
  xolos. Global scope'lar o‘z joyida: bunday foydalanuvchi URL'ga boshqa bo‘lim id sini yozsa bo‘sh sahifa oladi.
- Tanlov ro‘yxati `Department::visibleTo($user)` dan (Davomat sahifasidagidek).
- Bo‘lim almashganda ochiq guruh paneli yopiladi.

## Testlar

- Servis: `build(now, false, $departmentId)` faqat shu bo‘lim kartochkalarini va sanoqlarini beradi.
- Sahifa: `?department_id=` bilan faqat shu bo‘lim va mos `counts`; `filters.department_id`, `departments`,
  `departmentLocked` props; cheklangan foydalanuvchi boshqa bo‘lim id si bilan bo‘sh board oladi va ro‘yxatda faqat
  o‘z bo‘limi; `department_id=abc` — validatsiya xatosi.
