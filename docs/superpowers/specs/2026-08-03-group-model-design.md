# Group (sub-department) model

## Maqsad

Department ostida ierarxik guruhlar (`Group`) — `name`, `department_id`, `parent_id`.

## Schema

Jadval: `groups`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| department_id | FK → departments | cascade delete |
| parent_id | nullable FK → groups | null = ildiz; nullOnDelete |
| timestamps | | |

## Relatsiyalar

- `Group::department()` BelongsTo
- `Group::parent()` BelongsTo self
- `Group::children()` HasMany self
- `Department::groups()` HasMany

## Qamrov

Migration + Model + Factory. CRUD/UI keyingi bosqich.
