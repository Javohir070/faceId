# Employee Notes — ishdan ruxsat / izohlar

## Maqsad

Xodimlar uchun kunlik izoh (ruxsat) yozuvlari: kechikish, erta ketish, kelmaslik. Admin CRUD + xodim sahifasi + Telegram kunlik diggestda ruxsatli holatlarni hisobdan chiqarish.

## Qamrov

- Model: `Note` (jadval `notes`)
- Maydonlar: `employee_id`, `type`, `dt`, `note`
- `type` DB da tinyint `1|2|3`; kodda enum `came_late|left_early|absent`
- UI label (o‘zbekcha): Kechikdi / Erta ketdi / Kelmadi
- `dt`: faqat sana (`date`)
- `note`: majburiy text
- Unique: `(employee_id, dt, type)` — bir kunda bir tipdan bitta
- Admin: `/admin/notes` CRUD + filter
- Employee show: notes ro‘yxati + qo‘shish
- Telegram diggest: `came_late` → kechikkanlardan exclude; `absent` → kelmaganlardan exclude
- Permission: `Izohlar`

## Type mapping

| Qiymat | Enum | UI |
|--------|------|-----|
| 1 | `came_late` | Kechikdi |
| 2 | `left_early` | Erta ketdi |
| 3 | `absent` | Kelmadi |

## Arxitektura

**Backend**
- `App\Enums\NoteType` — backed int enum + `label(): string`
- `App\Models\Note` — casts `type` → NoteType, `dt` → date
- `Employee::notes()` HasMany
- Migration `create_notes_table`
- Repository / Service / Controller pattern (Employee ga o‘xshash)
- FormRequest: employee_id, type (in enum values), dt (date), note (required string)
- Unique validation: employee_id + dt + type (update da ignore self)
- Routes: resource `admin/notes` + optional nested create from employee

**Frontend**
- `Admin/Notes/Index.jsx` — filter (employee, type, date range), jadval, CRUD
- `Admin/Notes/Form.jsx` — employee select, type select, date, note textarea
- Employee Show: notes jadvali + “Izoh qo‘shish” (employee_id prefilled)
- Menu: `Izohlar` → `admin.notes.index`
- `config/menu_permissions.php` + seeder permission sync (super-admin)

**Telegram**
- `TelegramReportService::digestForDate`:
  - kun uchun `Note` larni yuklash
  - late hisobida `came_late` note bo‘lgan employee_id skip
  - absent hisobida `absent` note bo‘lgan employee_id skip
  - `left_early` diggestda hisoblanmaydi (faqat saqlanadi)

## Xatoliklar

- Unique buzilsa: validation error (o‘zbekcha xabar)
- Employee o‘chirilsa: notes cascade delete
- Ruxsatsiz: 403

## Test

- Migration/model: type cast + labels
- Unique constraint / validation
- Admin CRUD + permission 403
- Diggest: note bilan late/absent exclude

## Chegaralar (YAGNI)

- Telegram orqali note yaratish yo‘q
- `left_early` uchun alohida diggest hisobi yo‘q
- Workflow/approval (tasdiqlash) yo‘q
