# PRD Deviations Report — GASSIT
Generated: 2026-03-10 | Branch: improvements/platform-quality

## Summary
| Status | Count |
|--------|-------|
| Compliant | 45 |
| Extra (beyond spec) | 35 |
| Partial | 11 |
| Missing | 0 |
| Different | 1 |
| **Total** | **92** |

**Key finding:** The codebase is broadly compliant with all 92 stories. No story is fully missing. 35 stories received additional features beyond what was specified (mostly service extraction, extra endpoints, security hardening, and schema extensions). 11 stories have partial deviations — primarily URL path differences where views were placed under different module routes than specified. 1 story (US-092) uses a different but equivalent technology choice (Scramble vs l5-swagger).

---

## Stories with Deviations

### US-001: DB schema: users, roles, parks, park_user pivot
**Status:** extra
- **Extra:** Additional migrations added beyond spec — login_attempts tracking (2024_01_14), two_factor fields (2024_01_15), and personal_access_tokens created as a separate later migration (2026_03_07) rather than in the initial schema set.

---

### US-003: DB schema: customers, blacklist, customer documents
**Status:** extra
- **Extra:** Customer PII fields (first_name, last_name, email, phone, dob, id_number) are encrypted at rest using a custom EncryptedString cast (added via 2026_03_09_100000 migration). An email_hash column was also added for searching without decrypting (2026_03_09_100001). A dunning_pause field was added to the customers table (2026_03_08_000003). These are beyond the spec's schema definition.

---

### US-005: DB schema: contracts, contract signatures, contract renewals
**Status:** extra
- **Extra:** contracts table extended with final_invoice_waived (boolean) and is_termination_inspection (on damage_reports) via 2026_03_09_000003_add_termination_fields migration. billing_month column added to invoices via separate migration (2026_03_09_000001).

---

### US-007: DB schema: invoices, invoice items, payments, dunning
**Status:** extra
- **Extra:** invoice_items.item_type enum extended with 'credit_note' type via 2026_03_08_000001 migration. payments.retry_count column added via 2026_03_08_000002. invoices.billing_month column added via 2026_03_09_000001 for idempotency. electricity_readings.invoice_id foreign key added via 2026_03_09_000002.

---

### US-008: DB schema: damage reports, damage photos, vendors
**Status:** extra
- **Extra:** damage_reports table extended with is_termination_inspection boolean column (via 2026_03_09_000003) to support the termination inspection workflow.

---

### US-009: DB schema: electricity meters, readings, pricing
**Status:** extra
- **Extra:** electricity_readings.invoice_id foreign key column added (via 2026_03_09_000002) to track which readings have been billed.

---

### US-012: DB schema: discount rules, insurance options, revenue targets, system settings
**Status:** extra
- **Extra:** data_retention_years system setting added via separate migration (2026_03_09_000006). Performance indexes migration (2026_03_09_000005) added beyond spec.

---

### US-014: Laravel auth API: login, logout, refresh, password reset
**Status:** extra
- **Extra:** GET /api/v1/auth/me endpoint added (not in spec). POST /api/v1/auth/change-password endpoint added. All routes prefixed with /v1. ThrottleRequests applied (10/min login, 5/min password reset). block-2fa-tokens custom middleware applied to authenticated routes.

---

### US-016: RBAC middleware and park-scoping middleware
**Status:** extra
- **Extra:** Additional middleware implemented beyond spec: Block2faTokens (prevents using 2FA temp tokens on full API), SecurityHeaders (X-Frame-Options, CSP, etc.), AdminDocsAccess (protects API documentation).

---

### US-017: Admin: User and employee management API
**Status:** extra
- **Extra:** GET /api/v1/admin/employees/{id} (show single employee) added beyond spec which only lists GET/POST/PUT/DELETE.

---

### US-018: Park management API (CRUD, logo upload, settings)
**Status:** extra
- **Extra:** FileServeController added with signed URL file serving via web routes. Files served via signed temporary URLs providing an extra access control layer beyond direct S3 URLs.

---

### US-022: Customer API (CRUD, document upload, GDPR delete, blacklist)
**Status:** extra
- **Extra:** GET /api/v1/customers/{id}/data-export endpoint added for GDPR subject access request, implemented under admin role guard in CustomerController.

---

### US-024: Waiting list API (CRUD, notify, convert to application)
**Status:** extra
- **Extra:** GET /api/v1/waiting-list (global index, not park-scoped) added beyond spec. NotifyWaitingListEntries job also triggered when a termination inspection damage report is resolved — more complete than spec.

---

### US-025: Contract API (generate PDF, e-sign flow, activate, terminate, renew)
**Status:** extra
- **Extra:** ContractService extracted as a dedicated service class handling termination logic, pro-rated final invoice creation, and unit status transitions. contract.final_invoice_waived field added to allow deposit return override. is_termination_inspection flag enables automatic unit→free transition when inspection resolved.

---

### US-026: Deposit management API (track, process return, Mollie payout)
**Status:** extra
- **Extra:** Deposit return blocked if final invoice is unpaid unless final_invoice_waived=true — stricter than spec's 'can only return if contract terminated' rule.

---

### US-028: Invoice generation API (monthly auto-generate, manual, DATEV export)
**Status:** extra
- **Extra:** billing_month column added to invoices for idempotency. Idempotency check in GenerateInvoiceJob prevents duplicate invoices per contract+month. InvoiceService extracted as separate class. electricity_readings.invoice_id tracked to prevent double-billing.

---

### US-029: Mollie payment API (payment links, webhooks, refunds, retries)
**Status:** extra
- **Extra:** payments.retry_count column added per payment record. X-Mollie-Signature header verification in webhook. RetryPayment queued job handles retries. Mollie integration is a sandbox stub with complete architecture.

---

### US-030: Dunning automation: scheduler, escalation, dunning API
**Status:** extra
- **Extra:** DunningService extracted as dedicated service class. customers.dunning_pause boolean column added (2026_03_08_000003). Service encapsulates escalation logic, fee calculation, and email sending.

---

### US-031: Damage reports API (CRUD, status, photos, vendor assignment, damage invoice)
**Status:** extra
- **Extra:** damage_reports.is_termination_inspection boolean added enabling automatic unit→free transition and waiting list notification on resolve. GET /api/v1/damage-reports/{id} (show single) added.

---

### US-032: Electricity metering API (meters CRUD, readings, billing charge)
**Status:** extra
- **Extra:** PUT /api/v1/meters/{id} and DELETE /api/v1/meters/{id} endpoints added beyond spec. electricity_readings.invoice_id tracked to prevent double-billing.

---

### US-033: Vendor management API (CRUD, vendor invoices)
**Status:** extra
- **Extra:** GET /api/v1/vendors/{id} (show single vendor) added. PUT /api/v1/vendors/{id}/invoices/{invoiceId} (update vendor invoice) added. Beyond spec's listed endpoints.

---

### US-035: Mail system API (templates CRUD, send individual, mass mailing, schedule)
**Status:** extra
- **Extra:** POST /api/v1/mail/recipient-count endpoint added to allow live recipient count estimate before mass send.

---

### US-036: Document templates API and system settings API
**Status:** extra
- **Extra:** POST /api/v1/document-templates/{id}/preview endpoint added for generating a sample PDF preview from a template.

---

### US-039: Dashboard KPI and reports API
**Status:** extra
- **Extra:** GET /api/v1/reports/contracts endpoint added (not in spec's listed report endpoints). Reports accessible to a broader role set including rental_manager, office_worker, customer_service.

---

### US-042: Vue.js SPA scaffold
**Status:** extra
- **Extra:** Laravel Reverb WebSocket broadcasting configured (Broadcast::routes in api.php, laravel-echo in frontend plugins). useFormErrors composable added for standardized 422 validation handling.

---

### US-043: Shared UI component library
**Status:** extra
- **Extra:** AppSkeleton.vue and AppEmptyState.vue included in the shared component library alongside US-043 components (formally specified in US-083 but implemented together). WorkroomMenu.vue also present.

---

### US-059: Finance: Payments list and vendor invoices UI
**Status:** partial
- **Different:** Vendor management UI is implemented at /operations/vendors (VendorsView.vue) rather than /finance/vendors as specified. Vendor invoices accessible from operations module rather than finance.

---

### US-060: Damage reports list and detail UI
**Status:** partial
- **Different:** Damage reports placed at /operations/damage-reports (DamageReportsView.vue, DamageReportDetailView.vue) rather than /units/damage and /units/damage/{id} as specified.

---

### US-061: Task board UI (full Kanban with drag-and-drop)
**Status:** extra
- **Extra:** Drag-and-drop implemented using native HTML5 drag events rather than vue-draggable or @vueuse/gesture as specified (functionally equivalent). Tasks at /operations/tasks rather than /workroom/tasks.

---

### US-062: Park profile management UI
**Status:** partial
- **Missing:** Park profile management split across admin/ParksView.vue and admin/UsersView.vue. The spec expects /park/location with logo upload, primary color picker, and LLM access codes in one page accessible to Main Manager. Color picker and LLM codes section may not be consolidated as specified.

---

### US-063: Park unit types, floor plans, and insurance options UI
**Status:** partial
- **Different:** Unit types UI is at /units/unit-types (UnitTypesView.vue) rather than /park/types. Insurance options accessible from within unit types view rather than at separate /park/types/{id}/insurance path.

---

### US-064: Park discount rules and revenue targets UI
**Status:** partial
- **Different:** Discount rules UI at /finance/discount-rules (DiscountRulesView.vue) rather than /park/discounts. Revenue targets at /finance/revenue-targets (RevenueTargetsView.vue) rather than /park/revenue-targets.

---

### US-065: Park electricity configuration UI
**Status:** partial
- **Different:** Electricity configuration UI at /operations/electricity (ElectricityView.vue) rather than /park/electricity. Pricing history, new pricing, and units overview present but URL structure deviates from spec.

---

### US-066: Vendor management UI
**Status:** partial
- **Different:** Vendor management UI at /operations/vendors (VendorsView.vue) rather than /park/vendors as specified.

---

### US-067: Mail templates management UI
**Status:** extra
- **Extra:** TipTap WYSIWYG editor integrated (spec allowed TipTap or Quill — TipTap chosen, fully spec-compliant choice). Mail templates at /mail/mail-templates.

---

### US-068: Mass mailing and sent mail log UI
**Status:** extra
- **Extra:** POST /api/v1/mail/recipient-count integrated in MailComposeView.vue to show live recipient count estimate. Not in original spec.

---

### US-071: Admin: Users and employees management UI
**Status:** extra
- **Extra:** AdminUsersView.vue and AdminEmployeesView.vue separated into distinct view files rather than one combined admin page. Legacy admin/UsersView.vue also present.

---

### US-073: User profile and account settings UI
**Status:** partial
- **Missing:** Notification preferences toggle section exists in ProfileView.vue and calls PUT /auth/notification-preferences, but the corresponding backend API route does not exist in api.php — this feature is non-functional.

---

### US-074: In-app notification center UI
**Status:** extra
- **Extra:** Laravel Reverb WebSocket broadcasting configured in addition to 60-second polling, providing real-time notification delivery beyond spec's 'polled every 60s or via SSE'.

---

### US-076: Workroom quick-create menu UI
**Status:** partial
- **Missing:** Workroom keyboard shortcuts (N+A, N+S, etc.) are referenced in the component's title tooltip but full verification of shortcut handler logic for all combinations is incomplete. The keydown listener is registered but only Ctrl/Cmd+K is confirmed for search — the N+X shortcut combinations for each Workroom action need verification.

---

### US-085: E2E integration: monthly invoice generation scheduled job
**Status:** extra
- **Extra:** Idempotency via billing_month column (more robust than simple duplicate check). electricity_readings.invoice_id tracked for unbilled electricity inclusion. Laravel Horizon integrated for queue monitoring.

---

### US-086: E2E integration: contract termination → final invoice → deposit return flow
**Status:** extra
- **Extra:** Pro-rated final invoice computed in ContractService. is_termination_inspection flag enables automatic unit→free + waiting list notify. final_invoice_waived field allows explicit deposit return override. More complete than spec describes.

---

### US-087: Performance: API response caching and query optimization
**Status:** extra
- **Extra:** Performance indexes migration (2026_03_09_000005) adds more indexes than specified — also covers contracts(park_id, status), payments(invoice_id, status), dunning_records(customer_id), waiting_list(park_id, unit_type_id).

---

### US-088: Security hardening: CSRF, rate limiting, input sanitization, file validation
**Status:** partial
- **Missing:** HTMLPurifier is not in composer.json and no server-side HTML sanitization of mail template body was found. TipTap editor output stored without HTMLPurifier sanitization as specified.
- **Extra:** SecurityHeaders middleware provides X-Frame-Options, Content-Security-Policy, etc. AdminDocsAccess middleware protects API docs. Named throttle groups (api-read, api-write) applied broadly.

---

### US-089: GDPR compliance: consent flow, deletion, data export
**Status:** extra
- **Extra:** PII fields encrypted at rest using custom EncryptedString cast — exceeds spec which only requires anonymization on GDPR delete. email_hash column added for search without decryption. data_retention_years system setting via dedicated migration.

---

### US-090: Seeder: demo data for all modules
**Status:** partial
- **Different:** No separate minimal CI DemoSeeder — DatabaseSeeder calls DemoSeeder for both demo and CI purposes. All required seed data counts are correct (1 admin, 2 parks, 3 unit types/park, 20 units/park, 15 customers, 10 active contracts, 5 terminated, 20 invoices, 5 dunning records, 3 damage reports, 10 tasks, all template types, all reference categories).

---

### US-092: API documentation (OpenAPI / Swagger)
**Status:** different
- **Different:** Scramble (dedoc/scramble v0.13.14) used instead of l5-swagger. API docs at /docs/api (Scramble default) with redirect from /api/documentation. Scramble uses reflection-based generation rather than annotations. AdminDocsAccess middleware protects endpoint. Functionally equivalent but technically different from spec's annotation-based approach.
