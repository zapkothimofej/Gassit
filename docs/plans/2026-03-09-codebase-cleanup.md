# Codebase Cleanup Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix 6 identified code quality issues: UTF-8 encoding bug, progress.txt compression, dev environment docs, frontend folder restructure, API versioning, and E2E test coverage for critical flows.

**Architecture:** Mostly refactoring and reorganization — no new business logic. Frontend restructure is a pure rename+import update. API versioning wraps all existing routes under `/v1` prefix and updates the base URL env var.

**Tech Stack:** Vue 3 + TypeScript (frontend), Laravel 12 + PHP (backend), Vitest (frontend tests), PHPUnit (backend tests)

---

## Task 1: Fix prd.json UTF-8 Encoding

**Files:**
- Modify: `prd.json:13`

**Step 1: Fix encoding**

Replace `â€"` (mangled em dash) with `—`:

```json
"Create park_user pivot table: user_id, park_id, unique(user_id, park_id) — admin/main_manager access all parks implicitly",
```

**Step 2: Verify JSON is valid**

```bash
python3 -c "import json; json.load(open('prd.json')); print('OK')"
```
Expected: `OK`

**Step 3: Commit**

```bash
git add prd.json
git commit -m "fix: correct UTF-8 encoding in prd.json (em dash)"
```

---

## Task 2: Compress progress.txt

**Files:**
- Modify: `progress.txt`

**Context:** progress.txt is 1176 lines. The Codebase Patterns section at the top is the only content with ongoing value. The per-story entries are historical and can be archived.

**Step 1: Create archive**

```bash
cp progress.txt progress.archive.txt
```

**Step 2: Replace progress.txt with compressed version**

Keep the full `## Codebase Patterns` section (lines 1–9), then add a one-liner summary with link to archive:

```markdown
## Codebase Patterns
- Laravel backend lives in `backend/` subdirectory
- Use docker-compose for local dev: `docker compose up -d` (MySQL 8.0 already configured)
- DB: gassit on 127.0.0.1:3306, user: gassit, password: secret (from docker-compose.yml)
- Run migrations: `cd backend && php artisan migrate --force`
- PHP syntax check: `php -l <file>`
- Composer at ~/bin/composer (installed manually)
- Laravel 12 with Sanctum 4.x for API auth
- `park_user` pivot: admin/main_manager have implicit park access (enforced in app logic, not DB)
- All 92 PRD stories completed — full implementation history in progress.archive.txt

# Ralph Progress Log
Started: Sat Mar  7 00:34:23 WEST 2026
All 92 stories completed as of 2026-03-09. See progress.archive.txt for full history.
---
```

**Step 3: Commit**

```bash
git add progress.txt progress.archive.txt
git commit -m "chore: compress progress.txt, archive full history"
```

---

## Task 3: Update Dev Environment Docs

**Files:**
- Modify: `progress.txt` (Codebase Patterns section — already done in Task 2)
- Modify: `CLAUDE.md`

**Context:** The docker-compose.yml already has MySQL 8.0 fully configured. The progress.txt still documents the fragile portable MariaDB approach.

**Step 1: Update CLAUDE.md Codebase Patterns**

In `CLAUDE.md`, find any mention of portable MariaDB and replace with docker-compose instructions. Add a note to the project CLAUDE.md (at `D:/gassit/CLAUDE.md`) in a new `## Local Dev` section at the top:

```markdown
## Local Dev

- Start all services: `docker compose up -d`
- MySQL is at 127.0.0.1:3306, DB: gassit, user: gassit, password: secret
- Run migrations: `cd backend && php artisan migrate --force`
- Frontend dev server: `cd frontend && npm run dev` (or via docker-compose port 5173)
- Mail UI (MailHog): http://localhost:8025
- MinIO console: http://localhost:9001
```

**Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: document docker-compose dev setup, deprecate portable MariaDB"
```

---

## Task 4: Frontend Folder Restructure

**Files:**
- Move: `frontend/src/views/*.vue` → subdirectories
- Modify: `frontend/src/router/index.ts`

**Context:** 50+ view files all in one flat directory. Goal is to group by domain without touching component logic. All imports in the router use dynamic `import()` so only `router/index.ts` needs updating.

**New structure:**

```
views/
  auth/         LoginView, TwoFactorView, PasswordResetView, PasswordResetConfirmView,
                ForbiddenView, NotFoundView
  dashboard/    DashboardView
  units/        UnitsView, UnitDetailView, UnitTypesView, UnitReportsView
  customers/    CustomersView, CustomerDetailView, CustomerReportsView, BlacklistView
  applications/ ApplicationsView, ApplicationDetailView, ApplicationReportsView,
                WaitingListView
  contracts/    ContractsView, ContractDetailView
  finance/      InvoicesView, InvoiceDetailView, DepositsView, PaymentsView,
                DunningView, FinanceReportsView, DiscountRulesView, RevenueTargetsView
  operations/   DamageReportsView, DamageReportDetailView, VendorsView,
                ElectricityView, TasksView
  mail/         MailView, MailComposeView, MailSentView, MailTemplatesView
  reports/      ReportsView
  settings/     SettingsView, SystemSettingsView, DocumentTemplatesView,
                DocumentTemplateEditView
  admin/        AdminUsersView, AdminEmployeesView, ReferenceDataView,
                UsersView, ParksView
  user/         ProfileView, NotificationsView
```

**Step 1: Create subdirectories**

```bash
cd frontend/src/views
mkdir auth dashboard units customers applications contracts finance operations mail reports settings admin user
```

**Step 2: Move files**

```bash
# auth
mv LoginView.vue TwoFactorView.vue PasswordResetView.vue PasswordResetConfirmView.vue ForbiddenView.vue NotFoundView.vue auth/

# dashboard
mv DashboardView.vue dashboard/

# units
mv UnitsView.vue UnitDetailView.vue UnitTypesView.vue UnitReportsView.vue units/

# customers
mv CustomersView.vue CustomerDetailView.vue CustomerReportsView.vue BlacklistView.vue customers/

# applications
mv ApplicationsView.vue ApplicationDetailView.vue ApplicationReportsView.vue WaitingListView.vue applications/

# contracts
mv ContractsView.vue ContractDetailView.vue contracts/

# finance
mv InvoicesView.vue InvoiceDetailView.vue DepositsView.vue PaymentsView.vue DunningView.vue FinanceReportsView.vue DiscountRulesView.vue RevenueTargetsView.vue finance/

# operations
mv DamageReportsView.vue DamageReportDetailView.vue VendorsView.vue ElectricityView.vue TasksView.vue operations/

# mail
mv MailView.vue MailComposeView.vue MailSentView.vue MailTemplatesView.vue mail/

# reports
mv ReportsView.vue reports/

# settings
mv SettingsView.vue SystemSettingsView.vue DocumentTemplatesView.vue DocumentTemplateEditView.vue settings/

# admin
mv AdminUsersView.vue AdminEmployeesView.vue ReferenceDataView.vue UsersView.vue ParksView.vue admin/

# user
mv ProfileView.vue NotificationsView.vue user/
```

**Step 3: Update router/index.ts**

Replace all view imports to match new paths. Example diff:

```diff
- component: () => import('../views/LoginView.vue'),
+ component: () => import('../views/auth/LoginView.vue'),
```

Full updated `router/index.ts` — replace every `import('../views/XView.vue')` with its new subdirectory path per the structure above.

**Step 4: Verify build compiles**

```bash
cd frontend && npm run build
```
Expected: no errors, `dist/` created

**Step 5: Commit**

```bash
git add frontend/src/views/ frontend/src/router/index.ts
git commit -m "refactor: organize frontend views into domain subdirectories"
```

---

## Task 5: API Versioning (add /v1 prefix)

**Files:**
- Modify: `backend/routes/api.php`
- Modify: `frontend/.env`
- Modify: `frontend/.env.example`

**Context:** All API routes are currently at `/api/xyz`. We add a `/v1` prefix so they become `/api/v1/xyz`. The frontend uses `VITE_API_BASE_URL` — just update the env var. No frontend API call files need touching.

**Step 1: Wrap all routes in api.php in a v1 prefix group**

In `backend/routes/api.php`, wrap the entire file body (after the `use` statements) in:

```php
Route::prefix('v1')->group(function () {
    // ... all existing route definitions here
});
```

The opening `<?php` and all `use` statements stay outside. Only the `Route::prefix('auth')...` and subsequent route groups move inside.

**Step 2: Update frontend env vars**

`frontend/.env`:
```
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

`frontend/.env.example`:
```
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

**Step 3: Verify routes list**

```bash
cd backend && php artisan route:list --path=api | head -20
```
Expected: all routes now show `api/v1/...` prefix

**Step 4: Run backend tests**

```bash
cd backend && php artisan test --parallel
```
Expected: all tests pass (or same pass rate as before)

**Step 5: Commit**

```bash
git add backend/routes/api.php frontend/.env frontend/.env.example
git commit -m "feat: add /v1 prefix to all API routes"
```

---

## Task 6: E2E Tests for Critical Flows

**Files:**
- Create: `frontend/src/__tests__/auth.spec.ts`
- Create: `frontend/src/__tests__/rentalFlow.spec.ts`
- Modify: `frontend/package.json` (add vitest if not present)

**Context:** The 92 PRD stories had "typecheck passes" as acceptance criteria — no actual business logic tests for the frontend. Add lightweight integration tests for the two most critical user flows: login and the rental application → contract flow.

**Step 1: Check if Vitest is configured**

```bash
cat frontend/package.json | grep -E "vitest|test"
```

If not present, add Vitest:
```bash
cd frontend && npm install -D vitest @vue/test-utils jsdom @vitejs/plugin-vue
```

Add to `vite.config.ts`:
```ts
test: {
  environment: 'jsdom',
  globals: true,
}
```

Add to `package.json` scripts:
```json
"test": "vitest run"
```

**Step 2: Write auth store test**

`frontend/src/__tests__/auth.spec.ts`:
```ts
import { setActivePinia, createPinia } from 'pinia'
import { beforeEach, describe, it, expect, vi } from 'vitest'
import { useAuthStore } from '../stores/auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    sessionStorage.clear()
  })

  it('isAuthenticated is false when no token', () => {
    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(false)
  })

  it('logout clears token and user', () => {
    const auth = useAuthStore()
    sessionStorage.setItem('auth_token', 'test-token')
    auth.logout()
    expect(sessionStorage.getItem('auth_token')).toBeNull()
    expect(auth.user).toBeNull()
  })
})
```

**Step 3: Run test to verify it passes**

```bash
cd frontend && npm test
```
Expected: PASS

**Step 4: Write API module smoke test**

`frontend/src/__tests__/api.spec.ts`:
```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'

describe('api modules export expected functions', () => {
  it('applications api has expected methods', async () => {
    const api = await import('../api/applications')
    expect(typeof api.getApplications).toBe('function')
  })

  it('customers api has expected methods', async () => {
    const api = await import('../api/customers')
    expect(typeof api.getCustomers).toBe('function')
  })

  it('contracts api has expected methods', async () => {
    const api = await import('../api/contracts')
    expect(typeof api.getContracts).toBe('function')
  })
})
```

**Step 5: Run all tests**

```bash
cd frontend && npm test
```
Expected: all pass

**Step 6: Commit**

```bash
git add frontend/src/__tests__/ frontend/package.json frontend/vite.config.ts
git commit -m "test: add auth store and API module smoke tests"
```

---

## Execution Order

1 → 2 → 3 (quick wins, ~10 min total)
4 (frontend restructure, ~20 min)
5 (API versioning, ~15 min)
6 (tests, ~20 min — skip if API export names are unknown, check api/*.ts first)
