# Group CRUD/UI

## Maqsad

Department ostida ierarxik `Group` lar uchun to‘liq admin CRUD — alohida menyu va permission.

## Permission / menyu

- Permission nomi: `Guruhlar`
- Config key: `menu_permissions.resources.groups`
- Nav: Bo‘limlar yonida «Guruhlar» (icon: `ApartmentOutlined` yoki `ClusterOutlined` dan farqli — masalan `ApartmentOutlined`)
- `HandleInertiaRequests`: `can.groups`
- Migration: permission yaratish + `super-admin` ga berish

## Backend (Department stack)

- `GroupController`, `GroupService`, `GroupRepository` + Interface
- `StoreGroupRequest` / `UpdateGroupRequest`
- Routes: `Route::resource('groups', ...)` + `permission:Guruhlar`
- `routes.js`: `resource('admin.groups', '/admin/groups')`
- AppServiceProvider bind

### Validation

- `name`: required, string, max 255
- `department_id`: required, exists departments
- `parent_id`: nullable; exists groups; `department_id` bilan bir xil department; update da o‘zini / o‘z bolalarini parent qilish mumkin emas

### Delete

- Agar `children` bo‘lsa → session error, o‘chirilmaydi (Department+employees kabi)

## Frontend

- `Admin/Groups/Index.jsx` — jadval: №, nomi, bo‘lim, parent; filter `department_id`; CRUD actions
- `Admin/Groups/Form.jsx` — name, department select, parent select (department tanlangandan keyin shu department guruhlari; create/edit)
- `Admin/Groups/Show.jsx` — name, department, parent, created_at

## Test

- Feature: authorized CRUD, unauthorized 403, delete blocked when children exist, parent must same department

## Qamrovdan tashqari

- Employee ga `group_id` bog‘lash
- Tree-view Index
- Nested UI ichida Department Show
