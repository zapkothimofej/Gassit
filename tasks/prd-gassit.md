# PRD: GASSIT — Rental Management System

## Introduction

GASSIT is a web-based rental management platform for Laer Firmengruppe (LFG), a German property company operating multiple parks with mini storage-units, garages, and office spaces. The system replaces manual/spreadsheet-based workflows with a unified SaaS platform covering the full rental lifecycle: application intake, contract signing (incl. electronic signature), invoicing, payment (Mollie), debt collection, damage settlement, and termination. GDPR compliance is mandatory throughout.

**Tech Stack:** Vue.js (frontend), Laravel (backend), Java (auxiliary services), MySQL/PostgreSQL

---

## Goals

- Digitize the end-to-end rental lifecycle for all LFG parks from a single platform
- Enforce RBAC access control across 7 roles with fine-grained menu/action permissions
- Integrate with DATEV (accounting), Mollie (payments), Credit Bureau API, electronic signature provider, and LLM lock-access system
- Support 50–500 concurrent users with page load < 0.05s (cached views)
- Achieve GDPR compliance (data minimization, consent tracking, deletion rights)
- Provide real-time dashboard KPIs and Kanban task board for all managers

---

## Roles (RBAC)

| Role | Key Permissions |
|------|----------------|
| Admin | Full system access, user/employee management, global settings |
| Main Manager | All parks, all modules except Admin settings |
| Rental Manager | Own park: applications, contracts, customers, units, invoices, tasks |
| Park Worker | Own park: units (read), tasks (own), damage reports |
| Accountant | Finance module: payments, debtors, DATEV export, reports |
| Office Worker | Applications, customer profiles, mail |
| Customer Service | Read-only customer/application/unit data, create tickets |

---

## User Stories

### US-001: User Authentication & Session Management
**Description:** As any user, I want to log in securely and have my session managed with 2FA support so that unauthorized access is prevented.

**Acceptance Criteria:**
- [ ] Login with email + password
- [ ] Optional 2FA via authenticator app (TOTP)
- [ ] Session timeout after configurable idle period
- [ ] Password reset via email
- [ ] Failed login attempts locked after N tries (configurable)
- [ ] Typecheck/lint passes

### US-002: User & Employee Management (Admin)
**Description:** As an Admin, I want to create and manage users and employee records so that access is controlled and auditable.

**Acceptance Criteria:**
- [ ] Create/edit/deactivate user accounts with role assignment
- [ ] Assign user to one or more parks
- [ ] Employee profile: name, contact, role, park assignment, hire date
- [ ] Audit log of user creation and role changes
- [ ] Typecheck/lint passes

### US-003: Park Profile Management
**Description:** As a Main Manager, I want to create and configure parks so that each location has its own settings, pricing, and unit types.

**Acceptance Criteria:**
- [ ] Create park with: name, address, GPS coordinates, contact info, bank account
- [ ] Upload park logo and images
- [ ] Configure park-specific document/mail templates
- [ ] Define opening hours and access codes (LLM integration)
- [ ] Typecheck/lint passes

### US-004: Unit Profile Management
**Description:** As a Rental Manager, I want to manage individual unit profiles so that availability, pricing, and condition are always current.

**Acceptance Criteria:**
- [ ] Create unit with: number, type (mini-storage/garage/office), size (m²), floor, building
- [ ] Set base rent, deposit amount, included services
- [ ] Track unit status: Free / Reserved / Rented / Maintenance / Inactive
- [ ] Upload unit photos and condition reports
- [ ] View full rental history per unit
- [ ] Typecheck/lint passes

### US-005: Unit Types, Plans & Pricing Configuration
**Description:** As a Main Manager, I want to define unit types, floor plans, and discount rules so that pricing is consistent and configurable.

**Acceptance Criteria:**
- [ ] Create/edit unit types with default rent and deposit values
- [ ] Upload floor plan PDFs per type
- [ ] Configure time-based discounts (e.g., 10% off month 1–3)
- [ ] Configure insurance options per unit type
- [ ] Typecheck/lint passes

### US-006: Rental Application Intake
**Description:** As an Office Worker or Rental Manager, I want to create and process rental applications so that prospective tenants are evaluated and handled efficiently.

**Acceptance Criteria:**
- [ ] Create application: customer data, desired unit type, desired start date
- [ ] Application statuses: New → In Progress → Waiting → Completed
- [ ] Attach documents (ID, proof of income) to application
- [ ] Run Credit Bureau API check from application screen
- [ ] Assign application to Rental Manager
- [ ] Send automated acknowledgment email to applicant
- [ ] Typecheck/lint passes

### US-007: Waiting List Management
**Description:** As a Rental Manager, I want to manage a waiting list when no units are available so that demand is captured and served in order.

**Acceptance Criteria:**
- [ ] Add applicant to waiting list with desired unit type and priority score
- [ ] Auto-notify waiting list candidates when a matching unit becomes free
- [ ] Convert waiting list entry to application in one click
- [ ] Typecheck/lint passes

### US-008: Customer Profile Management
**Description:** As a Rental Manager, I want to maintain complete customer profiles so that all tenant information is centralized and GDPR-compliant.

**Acceptance Criteria:**
- [ ] Customer types: Private individual, Company
- [ ] Fields: name, DOB, address, ID number, contact, tax ID (for companies)
- [ ] Customer statuses: New → Tenant / Not renting / Debtor / Destroyer-Troublemaker / Blacklisted
- [ ] GDPR consent tracking with timestamp
- [ ] Data deletion request workflow (right to erasure)
- [ ] Blacklist management with reason and date
- [ ] Typecheck/lint passes

### US-009: Contract Generation & Electronic Signature
**Description:** As a Rental Manager, I want to generate rental contracts from templates and collect electronic signatures so that contracts are legally binding and paperless.

**Acceptance Criteria:**
- [ ] Generate PDF contract from configurable template with auto-filled tenant/unit data
- [ ] Contract statuses: Draft → Awaiting Signature → Signed → Active → Terminated / Declined
- [ ] Send contract via email for electronic signature (integration with e-sign provider)
- [ ] Store signed PDF with timestamp and audit trail
- [ ] Countersignature by authorized LFG employee
- [ ] Typecheck/lint passes

### US-010: Invoice Generation & Management
**Description:** As an Accountant or Rental Manager, I want invoices generated automatically and manually so that billing is accurate and timely.

**Acceptance Criteria:**
- [ ] Auto-generate monthly rent invoices for all active contracts on configurable day
- [ ] Manual invoice creation for one-off charges (damage, services)
- [ ] Invoice line items: rent, deposit, insurance, electricity, discounts
- [ ] Invoice statuses: Draft → Sent → Paid → Overdue → Cancelled
- [ ] PDF invoice generation with LFG branding
- [ ] Send invoice via email automatically on generation
- [ ] Export invoices to DATEV format
- [ ] Typecheck/lint passes

### US-011: Payment Processing (Mollie Integration)
**Description:** As an Accountant, I want payments collected via Mollie and reconciled automatically so that cash flow is tracked in real time.

**Acceptance Criteria:**
- [ ] Mollie payment link generated per invoice and sent to customer
- [ ] Supported methods: SEPA Direct Debit, iDEAL, credit card
- [ ] Webhook receives payment confirmation and updates invoice status to Paid
- [ ] Failed payments trigger retry logic (configurable attempts)
- [ ] Payment history displayed on customer and invoice screens
- [ ] Typecheck/lint passes

### US-012: Debt Collection & Dunning Process
**Description:** As an Accountant, I want an automated dunning workflow so that overdue invoices are escalated systematically.

**Acceptance Criteria:**
- [ ] Dunning levels: Level 1 (reminder) → Level 2 (formal notice) → Level 3 (legal)
- [ ] Configurable delay in days per dunning level
- [ ] Auto-send dunning letter via email at each level escalation
- [ ] Debtors list with total owed, dunning level, and last contact date
- [ ] Manual override to pause or escalate dunning for a customer
- [ ] Dunning fee added to invoice at Level 2+
- [ ] Typecheck/lint passes

### US-013: Contract Termination
**Description:** As a Rental Manager, I want to process contract terminations so that units are freed and customers offboarded correctly.

**Acceptance Criteria:**
- [ ] Termination types: by customer, by LFG (ordinary/extraordinary)
- [ ] Termination notice period enforced per contract terms
- [ ] Auto-calculate final invoice (pro-rated last month)
- [ ] Trigger damage inspection workflow on termination
- [ ] Unit status set to Maintenance/Free after termination date
- [ ] Typecheck/lint passes

### US-014: Deposit Management & Return
**Description:** As an Accountant, I want to track deposits and process returns after termination so that tenant funds are handled correctly.

**Acceptance Criteria:**
- [ ] Deposit amount stored per contract
- [ ] After termination + inspection: calculate net deposit return (deposit minus damages)
- [ ] Generate deposit return document
- [ ] Trigger Mollie payout or SEPA transfer for deposit return
- [ ] DATEV entry for deposit return
- [ ] Typecheck/lint passes

### US-015: Damage Settlement
**Description:** As a Park Worker or Rental Manager, I want to document and settle unit damages so that costs are tracked and billed.

**Acceptance Criteria:**
- [ ] Create damage report with: unit, description, photos, estimated cost, date
- [ ] Damage statuses: Reported → In Assessment → Repair Ordered → Resolved
- [ ] Assign repair task to Park Worker or external vendor
- [ ] Generate damage invoice for tenant if chargeable
- [ ] Link damage to contract termination for deposit deduction
- [ ] Typecheck/lint passes

### US-016: Electricity Metering
**Description:** As a Park Worker, I want to record electricity meter readings so that consumption is billed accurately.

**Acceptance Criteria:**
- [ ] Enter meter reading per unit with date and photo evidence
- [ ] System calculates consumption since last reading
- [ ] Apply configured price per kWh to generate electricity charge
- [ ] Add electricity charge to next invoice automatically
- [ ] Typecheck/lint passes

### US-017: Task Management (Kanban)
**Description:** As any manager or worker, I want a task board so that work items are tracked and assigned clearly.

**Acceptance Criteria:**
- [ ] Tasks can be created from Workroom quick-action menu
- [ ] Task types: Application follow-up, Damage repair, Customer ticket, General
- [ ] Kanban columns: To Do → In Progress → Done
- [ ] Assign task to user with due date and priority
- [ ] Tasks visible on dashboard Kanban widget filtered by own tasks
- [ ] Typecheck/lint passes

### US-018: Dashboard & KPI Overview
**Description:** As a manager, I want a real-time dashboard so that I can see the health of all parks at a glance.

**Acceptance Criteria:**
- [ ] KPI cards: New Requests (10), New Customers, New Invoices, Free Units, Ongoing, Cancellations, Problem Clients, Inactive Units, Debtors, Dunning Level, Damages, Repair Jobs
- [ ] Mahnstuffe (dunning) table: customer, amount, level, days overdue
- [ ] New Invoices table: recent invoices with status
- [ ] Park Revenue table: Planned vs Actual per park
- [ ] Aufgaben (tasks) Kanban widget
- [ ] Calendar widget for upcoming events
- [ ] Dashboard layout configurable per user role
- [ ] Verify in browser using dev-browser skill

### US-019: Mail & Mass Communication
**Description:** As an Office Worker, I want to send templated emails and mass mailings so that tenant communication is efficient and consistent.

**Acceptance Criteria:**
- [ ] Create/edit mail templates with variable placeholders ({{customer_name}}, etc.)
- [ ] Send individual emails from customer/contract context
- [ ] Mass mailing: filter recipients by park, status, contract type
- [ ] Schedule mass mailings
- [ ] Sent mail log with delivery status
- [ ] Typecheck/lint passes

### US-020: Document Template Management
**Description:** As an Admin, I want to manage contract and document templates so that all generated documents match LFG brand and legal requirements.

**Acceptance Criteria:**
- [ ] WYSIWYG or upload-based template editor for: rental contract, invoice, termination letter, dunning letters, deposit return
- [ ] Variable placeholders auto-mapped to system data
- [ ] Template versioning (previous versions retained)
- [ ] Per-park template overrides
- [ ] Typecheck/lint passes

### US-021: Reports & Analytics
**Description:** As a Main Manager or Accountant, I want comprehensive reports so that business performance is visible and auditable.

**Acceptance Criteria:**
- [ ] Application report: conversion rate, avg processing time, by park
- [ ] Customer report: churn, new/active/inactive by month
- [ ] Unit report: occupancy rate, avg rent per m², vacancy duration
- [ ] Finance report: revenue vs plan, outstanding debt, payments by method
- [ ] DATEV export per accounting period
- [ ] Export all reports to Excel/CSV
- [ ] Typecheck/lint passes

### US-022: Reference Books (Lookup Tables)
**Description:** As an Admin, I want to manage system-wide reference data so that dropdowns and categories are consistent.

**Acceptance Criteria:**
- [ ] Manage: countries, cities, document types, contract termination reasons, damage categories, unit features
- [ ] Add/edit/deactivate reference entries
- [ ] Changes reflected immediately in all dropdowns
- [ ] Typecheck/lint passes

### US-023: UI Customization & White-Label
**Description:** As an Admin, I want to configure the UI with LFG branding so that the platform feels native to the company.

**Acceptance Criteria:**
- [ ] Upload company logo (shown in header and on generated PDFs)
- [ ] Set primary/secondary color scheme
- [ ] Configure default language (German)
- [ ] Per-park color scheme override
- [ ] Verify in browser using dev-browser skill

---

## Functional Requirements

- FR-1: RBAC enforced at API level — every endpoint checks role + park assignment
- FR-2: All data mutations are logged in an audit trail (who, what, when)
- FR-3: Contract PDF generation uses server-side rendering (Laravel + wkhtmltopdf or Puppeteer)
- FR-4: Mollie webhook endpoint processes payment events idempotently
- FR-5: DATEV export produces EXTF format file (.csv) per accounting period
- FR-6: Credit Bureau API check stored as PDF attachment on customer profile
- FR-7: Electronic signature via configurable provider (DocuSign/HelloSign compatible API)
- FR-8: LLM lock-access system integration via REST API for park access codes
- FR-9: Electricity metering supports multiple meters per unit
- FR-10: Multi-park: users scoped to assigned parks unless role = Admin or Main Manager
- FR-11: All personally identifiable data encrypted at rest (AES-256)
- FR-12: GDPR deletion: anonymize customer PII on deletion request, retain financial records
- FR-13: System must handle 500 concurrent users; page loads < 0.05s (CDN + cache)
- FR-14: Vue.js SPA with Laravel API backend; JWT or Laravel Sanctum auth tokens
- FR-15: Excel/ODBC export available for all tabular reports

---

## Non-Goals (Out of Scope)

- No mobile app (responsive web only)
- No CRM integration in v1 (marked as "future")
- No online self-service portal for tenants
- No automated property valuation or market pricing
- No multi-currency support (EUR only)
- No payroll or HR beyond employee profile storage

---

## Menu Structure

```
Dashboard
Workroom (quick-create: Application / Damage / Ticket / Invoice / Task)
Request
  ├── Applications
  ├── Waiting List
  └── Reports
Customer
  ├── Customers
  ├── Blacklist
  └── Reports
Units
  ├── Units
  ├── Damage
  └── Reports
Park
  ├── Location
  ├── Electricity
  ├── Types
  ├── Plans
  ├── Discounts
  └── Insurance
Finance
  ├── Payments
  ├── Debtors
  ├── Vendors
  └── Reports
Reports
Mail
  ├── Mass Mailing
  ├── Schedule
  └── Sent
Settings
  ├── Dashboard
  ├── Document Templates
  └── Mail Templates
Admin
  ├── Users
  ├── Employees
  └── Settings
```

---

## Technical Considerations

- **Frontend:** Vue.js 3 + Composition API, Pinia state management, Vue Router, Vite build
- **Backend:** Laravel 11, REST API, Laravel Sanctum (JWT), Eloquent ORM
- **Database:** MySQL 8 with soft deletes for GDPR; separate schema per park or row-level park_id scoping
- **Queue:** Laravel Queues (Redis) for invoice generation, email sending, dunning escalation
- **File Storage:** S3-compatible object storage for PDFs, photos, documents
- **PDF Generation:** Laravel-dompdf or Browsershot (Puppeteer)
- **Testing:** PHPUnit (backend), Vitest + Vue Test Utils (frontend)
- **Deployment:** Docker containers on PaaS (cloud-provider TBD); CI/CD via GitHub Actions
- **Integrations:**
  - Mollie Payments API (webhooks + payment links)
  - DATEV EXTF export
  - Credit Bureau (Schufa or Creditreform) REST API
  - Electronic signature provider (DocuSign/HelloSign)
  - LLM lock-access REST API
  - Excel/ODBC export library

---

## Design Considerations

- Dashboard reference: `02_Dashboard neu.pdf` — KPI card grid, Mahnstuffe table, revenue table, Kanban, Calendar
- Language: German UI throughout
- Color scheme: configurable per Admin settings (white-label)
- Typography: clean sans-serif (Inter or similar)
- All tables: sortable columns, pagination, search/filter, Excel export button

---

## Success Metrics

- 100% of rental lifecycle steps completable within GASSIT (no fallback to spreadsheets)
- Contract-to-signature time reduced by 70% vs paper process
- Invoice generation error rate < 0.1%
- Dunning process fully automated (zero manual escalation emails)
- Dashboard KPIs update within 30 seconds of underlying data change
- Zero GDPR incidents in first 12 months

---

## Open Questions

- Which e-signature provider (DocuSign vs HelloSign vs EU-native)?
- Which Credit Bureau provider (Schufa vs Creditreform)?
- Is the Java component the LLM lock-access bridge or a separate legacy service?
- Should waiting list priority be manual score or first-come-first-served?
- Content of the .docx files (Box prices, Units & Prices, Wichtig für LFG, Russian-language specs) — these may contain additional pricing rules and edge cases not captured here; re-read when tooling (LibreOffice/python-docx) is available.
