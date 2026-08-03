# User Department Scope Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bind users to departments and scope Employee/Group/Report (Notes via employee) for all non–super-admin roles; remove `users.domain`.

**Architecture:** Eloquent global scope on department-linked models; User CRUD requires `department_id` unless role includes `super-admin`; force department on writes for scoped users.

**Tech Stack:** Laravel, Spatie Permission, Inertia/React, PHPUnit

## Global Constraints

- Scope only when authenticated and not `super-admin`
- Do not scope Devices/Schedules/Categories/Users listing
- Uzbek UI labels

---

### Task 1: Schema + User model

- [ ] Migration: drop `domain`, add `department_id`
- [ ] Edit create_users migration: remove `domain`
- [ ] User model: fillable, `department()`, `isDepartmentScoped()`
- [ ] UserFactory optional `department_id`

### Task 2: Global scope

- [ ] `App\Models\Scopes\BelongsToDepartmentScope`
- [ ] Apply on Employee, Group, Report
- [ ] Fix `Employee::generateUniqueCard` withoutGlobalScopes
- [ ] NoteRepository: `whereHas('employee')`

### Task 3: User admin UI/API

- [ ] Store/UpdateUserRequest validation
- [ ] UserController props + departments list
- [ ] Form/Show/Index department field
- [ ] UserRepository paginate with department

### Task 4: Write-path + dropdowns

- [ ] Store/Update Employee & Group: merge department for scoped users
- [ ] Department queries for forms filtered when scoped

### Task 5: Tests

- [ ] Helper: test admins get `super-admin` role (bypass scope) OR set department
- [ ] Feature: scoped user sees only own department employees/groups/reports
- [ ] Feature: user create requires department_id without super-admin
- [ ] Fix RolePermissionUserAdminTest store payload
