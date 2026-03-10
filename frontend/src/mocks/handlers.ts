import { http, HttpResponse } from 'msw'

const BASE = 'http://localhost:8000/api'

// ─── Seed data ───────────────────────────────────────────────────────────────

const parks = [
  { id: 1, name: 'Park Mühlheim', address: 'Industriestr. 1', city: 'Mühlheim', zip: '45468', country: 'DE', phone: '+49 208 1234567', email: 'muehlheim@gassit.de', bank_iban: 'DE89370400440532013000', bank_bic: 'COBADEFFXXX', bank_owner: 'Laer GmbH', logo_path: null, primary_color: '#3b82f6', language: 'de' },
  { id: 2, name: 'Park Duisburg', address: 'Hafen Allee 5', city: 'Duisburg', zip: '47051', country: 'DE', phone: '+49 203 9876543', email: 'duisburg@gassit.de', bank_iban: 'DE89370400440532013001', bank_bic: 'COBADEFFXXX', bank_owner: 'Laer GmbH', logo_path: null, primary_color: '#10b981', language: 'de' },
]

const users = [
  { id: 1, name: 'Admin User', email: 'admin@gassit.de', role: 'admin', active: true, parks: parks },
  { id: 2, name: 'Max Müller', email: 'manager@gassit.de', role: 'main_manager', active: true, parks: [parks[0]] },
  { id: 3, name: 'Sarah Schmidt', email: 'rental@gassit.de', role: 'rental_manager', active: true, parks: [parks[0]] },
]

const customers = [
  { id: 1, type: 'private', status: 'tenant', first_name: 'Hans', last_name: 'Wagner', company_name: null, email: 'hans.wagner@mail.de', phone: '+49 170 1234567', id_number: 'DE1234567', tax_id: null, address: 'Hauptstr. 12', city: 'Mühlheim', zip: '45468', country: 'DE', dob: '1980-05-15', gdpr_consent_at: '2024-01-10T10:00:00Z', gdpr_deleted_at: null, notes: null, park: parks[0], created_at: '2024-01-10T10:00:00Z' },
  { id: 2, type: 'company', status: 'tenant', first_name: 'Maria', last_name: 'Becker', company_name: 'Becker GmbH', email: 'maria@becker-gmbh.de', phone: '+49 201 9876543', id_number: null, tax_id: 'DE812345678', address: 'Gewerbepark 3', city: 'Duisburg', zip: '47051', country: 'DE', dob: null, gdpr_consent_at: '2024-02-01T09:00:00Z', gdpr_deleted_at: null, notes: 'VIP Kunde', park: parks[1], created_at: '2024-02-01T09:00:00Z' },
  { id: 3, type: 'private', status: 'debtor', first_name: 'Peter', last_name: 'Schulz', company_name: null, email: 'p.schulz@mail.de', phone: '+49 177 2345678', id_number: 'DE7654321', tax_id: null, address: 'Waldweg 7', city: 'Mühlheim', zip: '45470', country: 'DE', dob: '1975-11-20', gdpr_consent_at: '2023-06-01T12:00:00Z', gdpr_deleted_at: null, notes: '2 Mahnungen', park: parks[0], created_at: '2023-06-01T12:00:00Z' },
  { id: 4, type: 'private', status: 'new', first_name: 'Anna', last_name: 'Klein', company_name: null, email: 'anna.klein@web.de', phone: '+49 155 3456789', id_number: null, tax_id: null, address: 'Rosenweg 2', city: 'Duisburg', zip: '47053', country: 'DE', dob: '1992-03-08', gdpr_consent_at: '2025-01-15T08:00:00Z', gdpr_deleted_at: null, notes: null, park: parks[1], created_at: '2025-01-15T08:00:00Z' },
]

const unitTypes = [
  { id: 1, park_id: 1, park: parks[0], name: 'Standard Box 5m²', description: 'Kleine Lagerbox', base_rent: '89.00', deposit_amount: '178.00', size_m2: '5.00', floor_plan_path: null, features: ['electricity'], photos: [] },
  { id: 2, park_id: 1, park: parks[0], name: 'Medium Box 10m²', description: 'Mittlere Lagerbox', base_rent: '149.00', deposit_amount: '298.00', size_m2: '10.00', floor_plan_path: null, features: ['electricity', 'heating'], photos: [] },
  { id: 3, park_id: 2, park: parks[1], name: 'Large Box 20m²', description: 'Große Lagerbox', base_rent: '249.00', deposit_amount: '498.00', size_m2: '20.00', floor_plan_path: null, features: ['electricity', 'heating', 'wifi'], photos: [] },
]

const units = [
  { id: 1, park_id: 1, park: parks[0], unit_type_id: 1, unit_type: unitTypes[0], unit_number: 'A-001', floor: 0, building: 'A', size_m2: '5.00', rent_override: null, deposit_override: null, status: 'rented', notes: null },
  { id: 2, park_id: 1, park: parks[0], unit_type_id: 2, unit_type: unitTypes[1], unit_number: 'A-002', floor: 0, building: 'A', size_m2: '10.00', rent_override: null, deposit_override: null, status: 'free', notes: null },
  { id: 3, park_id: 1, park: parks[0], unit_type_id: 2, unit_type: unitTypes[1], unit_number: 'B-001', floor: 1, building: 'B', size_m2: '10.00', rent_override: '139.00', deposit_override: null, status: 'rented', notes: 'Erdgeschoss gesperrt' },
  { id: 4, park_id: 2, park: parks[1], unit_type_id: 3, unit_type: unitTypes[2], unit_number: 'C-001', floor: 0, building: 'C', size_m2: '20.00', rent_override: null, deposit_override: null, status: 'free', notes: null },
  { id: 5, park_id: 1, park: parks[0], unit_type_id: 1, unit_type: unitTypes[0], unit_number: 'A-003', floor: 0, building: 'A', size_m2: '5.00', rent_override: null, deposit_override: null, status: 'maintenance', notes: 'Tür defekt' },
]

const contracts = [
  { id: 1, contract_number: 'CNT-2024-001', status: 'active', start_date: '2024-02-01', end_date: null, rent_amount: '89.00', deposit_amount: '178.00', insurance_amount: '0.00', notice_period_days: 30, notes: null, signed_at: '2024-01-28T14:00:00Z', terminated_at: null, termination_notice_date: null, customer: customers[0], unit: units[0], park: parks[0] },
  { id: 2, contract_number: 'CNT-2024-002', status: 'active', start_date: '2024-03-01', end_date: null, rent_amount: '149.00', deposit_amount: '298.00', insurance_amount: '0.00', notice_period_days: 30, notes: 'Sonderkonditionen', signed_at: '2024-02-25T11:00:00Z', terminated_at: null, termination_notice_date: null, customer: customers[1], unit: units[2], park: parks[0] },
  { id: 3, contract_number: 'CNT-2023-015', status: 'terminated', start_date: '2023-06-01', end_date: '2024-01-31', rent_amount: '89.00', deposit_amount: '178.00', insurance_amount: '0.00', notice_period_days: 30, notes: null, signed_at: '2023-05-28T09:00:00Z', terminated_at: '2024-01-15T00:00:00Z', termination_notice_date: '2024-01-01', customer: customers[2], unit: units[1], park: parks[0] },
]

const invoices = [
  { id: 1, invoice_number: 'INV-2025-0001', status: 'paid', total_amount: '89.00', amount: '89.00', due_date: '2025-02-01', issued_at: '2025-01-25T08:00:00Z', contract_id: 1, customer: customers[0], park: parks[0] },
  { id: 2, invoice_number: 'INV-2025-0002', status: 'overdue', total_amount: '149.00', amount: '149.00', due_date: '2025-01-01', issued_at: '2024-12-20T08:00:00Z', contract_id: 2, customer: customers[1], park: parks[0] },
  { id: 3, invoice_number: 'INV-2025-0003', status: 'pending', total_amount: '89.00', amount: '89.00', due_date: '2025-03-01', issued_at: '2025-02-20T08:00:00Z', contract_id: 1, customer: customers[0], park: parks[0] },
  { id: 4, invoice_number: 'INV-2025-0004', status: 'draft', total_amount: '149.00', amount: '149.00', due_date: '2025-03-15', issued_at: '2025-02-28T08:00:00Z', contract_id: 2, customer: customers[1], park: parks[0] },
]

const applications = [
  { id: 1, customer: customers[0], unit_type: unitTypes[0], park: parks[0], status: 'new', assigned_to: users[2], source: 'website', notes: 'Dringend', desired_start_date: '2025-04-01', created_at: '2025-02-15T10:00:00Z' },
  { id: 2, customer: customers[3], unit_type: unitTypes[1], park: parks[1], status: 'in_review', assigned_to: null, source: 'phone', notes: null, desired_start_date: '2025-05-01', created_at: '2025-02-20T14:00:00Z' },
  { id: 3, customer: customers[1], unit_type: unitTypes[2], park: parks[1], status: 'approved', assigned_to: users[1], source: 'referral', notes: 'Stammkunde', desired_start_date: '2025-03-15', created_at: '2025-01-30T09:00:00Z' },
]

const tasks = [
  { id: 1, title: 'Vertrag CNT-2024-001 verlängern', status: 'todo', priority: 'high', due_date: '2025-03-15', park_id: 1, assigned_to: users[2] },
  { id: 2, title: 'Zahlung von Peter Schulz nachfragen', status: 'in_progress', priority: 'high', due_date: '2025-03-12', park_id: 1, assigned_to: users[1] },
  { id: 3, title: 'Jahresinspektionen planen', status: 'todo', priority: 'medium', due_date: '2025-04-01', park_id: 1, assigned_to: null },
  { id: 4, title: 'Neue Preisliste erstellen', status: 'done', priority: 'low', due_date: '2025-02-28', park_id: 2, assigned_to: users[1] },
  { id: 5, title: 'Tür A-003 reparieren', status: 'in_progress', priority: 'medium', due_date: '2025-03-20', park_id: 1, assigned_to: users[2] },
]

const damageReports = [
  { id: 1, unit: units[4], reported_by: customers[0], assigned_vendor: null, title: 'Tür klemmt', description: 'Eingangstür lässt sich schwer öffnen', status: 'open', priority: 'medium', reported_at: '2025-02-10T09:00:00Z', resolved_at: null, photos: [] },
  { id: 2, unit: units[2], reported_by: customers[1], assigned_vendor: null, title: 'Wasserfleck an der Decke', description: 'Nach Regen Fleck sichtbar', status: 'in_progress', priority: 'high', reported_at: '2025-01-20T14:00:00Z', resolved_at: null, photos: [] },
]

const vendors = [
  { id: 1, name: 'Schnell Reparaturen GmbH', contact_name: 'Klaus Schnell', email: 'info@schnell-rep.de', phone: '+49 201 1111111', specialization: 'Schlosserei', rating: 5, notes: null, active: true },
  { id: 2, name: 'Klempner Meier', contact_name: 'Otto Meier', email: 'otto@meier-klempner.de', phone: '+49 208 2222222', specialization: 'Sanitär', rating: 4, notes: 'Nur morgens erreichbar', active: true },
]

const mailTemplates = [
  { id: 1, name: 'Vertragsbestätigung', subject: 'Ihr Mietvertrag bei GASSIT', body: '<p>Sehr geehrte/r {{customer_name}},</p><p>hiermit bestätigen wir Ihren Mietvertrag {{contract_number}}.</p>', variables: ['customer_name', 'contract_number'], active: true },
  { id: 2, name: 'Zahlungserinnerung', subject: 'Zahlungserinnerung - Rechnung {{invoice_number}}', body: '<p>Sehr geehrte/r {{customer_name}},</p><p>wir möchten Sie an die ausstehende Zahlung erinnern.</p>', variables: ['customer_name', 'invoice_number'], active: true },
]

const notifications = [
  { id: 1, type: 'invoice_overdue', title: 'Rechnung überfällig', message: 'INV-2025-0002 ist seit 10 Tagen überfällig', read: false, created_at: '2025-03-01T08:00:00Z', data: {} },
  { id: 2, type: 'contract_expiring', title: 'Vertrag läuft aus', message: 'CNT-2023-015 läuft in 30 Tagen aus', read: false, created_at: '2025-02-28T12:00:00Z', data: {} },
  { id: 3, type: 'damage_report', title: 'Neuer Schaden gemeldet', message: 'Tür klemmt in A-003', read: true, created_at: '2025-02-10T09:00:00Z', data: {} },
]

const waitingList = [
  { id: 1, customer: customers[3], unit_type: unitTypes[0], park: parks[0], notes: 'Wartet seit Januar', created_at: '2025-01-15T10:00:00Z' },
]

const discountRules = [
  { id: 1, name: 'Langzeitmieter 5%', type: 'percentage', value: '5.00', min_contract_months: 12, applies_to: 'all', active: true },
  { id: 2, name: 'Stammkunde 10€', type: 'fixed', value: '10.00', min_contract_months: null, applies_to: 'all', active: true },
]

const revenueTargets = [
  { id: 1, park_id: 1, park: parks[0], year: 2025, month: 3, target: '5000.00' },
  { id: 2, park_id: 2, park: parks[1], year: 2025, month: 3, target: '8000.00' },
]

const documentTemplates = [
  { id: 1, name: 'Mietvertrag Standard', type: 'contract', content: '<h1>Mietvertrag</h1><p>Zwischen {{landlord}} und {{tenant}}...</p>', variables: ['landlord', 'tenant', 'unit', 'rent'], active: true, updated_at: '2025-01-01T00:00:00Z' },
]

const systemSettings = {
  company_name: 'Laer Firmengruppe',
  default_dunning_fee: '5.00',
  dunning_levels: 3,
  default_notice_period: 30,
  vat_rate: '19.00',
  invoice_prefix: 'INV',
  contract_prefix: 'CNT',
  mollie_api_key: '***',
  mollie_enabled: true,
  email_sender: 'noreply@gassit.de',
  meilisearch_enabled: true,
}

const electricityPricing = [
  { id: 1, park_id: 1, park: parks[0], price_per_kwh: '0.32', valid_from: '2025-01-01', valid_to: null },
]

const referenceData = {
  termination_reasons: [
    { id: 1, label: 'Eigennutzung' },
    { id: 2, label: 'Zahlungsverzug' },
    { id: 3, label: 'Vertragsverletzung' },
    { id: 4, label: 'Sonstiges' },
  ],
  insurance_options: [
    { id: 1, unit_type_id: null, name: 'Basis', price: '9.90', description: 'Grundschutz' },
    { id: 2, unit_type_id: null, name: 'Premium', price: '19.90', description: 'Vollschutz' },
  ],
}

// ─── Helper ──────────────────────────────────────────────────────────────────

function paginate<T>(items: T[], page = 1, perPage = 20) {
  const total = items.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const from = (page - 1) * perPage
  return { data: items.slice(from, from + perPage), total, last_page: lastPage, current_page: page }
}

// ─── Handlers ────────────────────────────────────────────────────────────────

export const handlers = [

  // Auth
  http.post(`${BASE}/auth/login`, async ({ request }) => {
    const body = await request.json() as { email?: string }
    const user = users.find(u => u.email === body?.email) ?? users[0]
    return HttpResponse.json({ token: 'mock-token-abc123', user })
  }),
  http.get(`${BASE}/auth/me`, () => HttpResponse.json(users[0])),
  http.post(`${BASE}/auth/logout`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/auth/forgot-password`, () => HttpResponse.json({ message: 'E-Mail gesendet' })),
  http.post(`${BASE}/auth/reset-password`, () => HttpResponse.json({ message: 'Passwort zurückgesetzt' })),
  http.post(`${BASE}/auth/change-password`, () => HttpResponse.json({ message: 'ok' })),
  http.get(`${BASE}/auth/notification-preferences`, () => HttpResponse.json({ email: true, in_app: true })),
  http.put(`${BASE}/auth/notification-preferences`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/auth/2fa/setup`, () => HttpResponse.json({ qr_code: 'data:image/png;base64,iVBOR...', secret: 'MOCK2FASECRET' })),
  http.post(`${BASE}/auth/2fa/enable`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/auth/2fa/disable`, () => HttpResponse.json({ message: 'ok' })),

  // Parks
  http.get(`${BASE}/parks`, () => HttpResponse.json(paginate(parks))),
  http.get(`${BASE}/parks/:id`, ({ params }) => {
    const park = parks.find(p => p.id === Number(params.id)) ?? parks[0]
    return HttpResponse.json(park)
  }),
  http.post(`${BASE}/parks`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body }, { status: 201 })
  }),
  http.put(`${BASE}/parks/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const park = parks.find(p => p.id === Number(params.id)) ?? parks[0]
    return HttpResponse.json({ ...park, ...body })
  }),

  // Dashboard
  http.get(`${BASE}/dashboard/kpis`, () => HttpResponse.json({
    new_requests: 3, new_customers: 2, new_invoices_count: 4, free_units: 2,
    ongoing_contracts: 2, cancellations: 1, problem_clients: 1, inactive_units: 1,
    debtors_count: 1, max_dunning_level: 2, damages_open: 2, repair_jobs_open: 1,
  })),
  http.get(`${BASE}/dashboard/mahnstuffe`, () => HttpResponse.json([
    { customer_id: 3, customer_name: 'Peter Schulz', total_owed: 267.50, dunning_level: 2, days_overdue: 45 },
  ])),
  http.get(`${BASE}/dashboard/revenue`, () => HttpResponse.json([
    { park_id: 1, planned: 5000, actual: 4823.50 },
    { park_id: 2, planned: 8000, actual: 8250.00 },
  ])),

  // Units
  http.get(`${BASE}/units`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const perPage = Number(url.searchParams.get('per_page') ?? 20)
    const search = url.searchParams.get('search') ?? ''
    const filtered = search ? units.filter(u => u.unit_number.toLowerCase().includes(search.toLowerCase())) : units
    return HttpResponse.json(paginate(filtered, page, perPage))
  }),
  http.get(`${BASE}/units/:id`, ({ params }) => {
    const unit = units.find(u => u.id === Number(params.id)) ?? units[0]
    return HttpResponse.json({ ...unit, contracts: [contracts[0]], damage_reports: [damageReports[0]], meters: [] })
  }),
  http.post(`${BASE}/units`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body, park: parks[0], unit_type: unitTypes[0] }, { status: 201 })
  }),
  http.put(`${BASE}/units/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const unit = units.find(u => u.id === Number(params.id)) ?? units[0]
    return HttpResponse.json({ ...unit, ...body })
  }),
  http.patch(`${BASE}/units/:id/status`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const unit = units.find(u => u.id === Number(params.id)) ?? units[0]
    return HttpResponse.json({ ...unit, status: body.status })
  }),

  // Unit Types
  http.get(`${BASE}/unit-types`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(unitTypes, page))
  }),
  http.get(`${BASE}/unit-types/:id`, ({ params }) => {
    const ut = unitTypes.find(u => u.id === Number(params.id)) ?? unitTypes[0]
    return HttpResponse.json(ut)
  }),
  http.post(`${BASE}/unit-types`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, park: parks[0], ...body, photos: [], features: [] }, { status: 201 })
  }),
  http.put(`${BASE}/unit-types/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const ut = unitTypes.find(u => u.id === Number(params.id)) ?? unitTypes[0]
    return HttpResponse.json({ ...ut, ...body })
  }),
  http.delete(`${BASE}/unit-types/:id`, () => new HttpResponse(null, { status: 204 })),
  http.get(`${BASE}/unit-types/:id/features`, ({ params }) => {
    const ut = unitTypes.find(u => u.id === Number(params.id)) ?? unitTypes[0]!
    return HttpResponse.json(ut?.features ?? [])
  }),

  // Customers
  http.get(`${BASE}/customers`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const perPage = Number(url.searchParams.get('per_page') ?? 20)
    const search = url.searchParams.get('search') ?? ''
    const filtered = search
      ? customers.filter(c => (c.first_name + ' ' + c.last_name).toLowerCase().includes(search.toLowerCase()) || c.email.includes(search))
      : customers
    return HttpResponse.json(paginate(filtered, page, perPage))
  }),
  http.get(`${BASE}/customers/:id`, ({ params }) => {
    const c = customers.find(c => c.id === Number(params.id)) ?? customers[0]
    return HttpResponse.json({ ...c, contracts: [contracts[0]], documents: [], blacklist: [] })
  }),
  http.post(`${BASE}/customers`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body, park: parks[0], created_at: new Date().toISOString() }, { status: 201 })
  }),
  http.put(`${BASE}/customers/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const c = customers.find(c => c.id === Number(params.id)) ?? customers[0]
    return HttpResponse.json({ ...c, ...body })
  }),
  http.get(`${BASE}/customers/blacklist`, () => HttpResponse.json(paginate([]))),
  http.get(`${BASE}/blacklist`, () => HttpResponse.json(paginate([
    { id: 1, customer: customers[2], reason: 'Mehrfache Zahlungsverzüge', added_by: users[0], added_at: '2024-06-01T00:00:00Z', removed_at: null },
  ]))),
  http.post(`${BASE}/customers/:id/blacklist`, () => HttpResponse.json({ message: 'ok' })),
  http.delete(`${BASE}/customers/:id/blacklist`, () => HttpResponse.json({ message: 'ok' })),
  http.get(`${BASE}/customers/:id/credit-check`, () => HttpResponse.json({ score: 750, status: 'ok', provider: 'mock' })),
  http.post(`${BASE}/customers/:id/documents`, () => HttpResponse.json({ id: 1, path: '/docs/mock.pdf', filename: 'mock.pdf' })),

  // Contracts
  http.get(`${BASE}/contracts`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const perPage = Number(url.searchParams.get('per_page') ?? 20)
    return HttpResponse.json(paginate(contracts, page, perPage))
  }),
  http.get(`${BASE}/contracts/:id`, ({ params }) => {
    const c = contracts.find(c => c.id === Number(params.id)) ?? contracts[0]!
    return HttpResponse.json({ ...c, signatures: [], deposit: { id: 1, amount: c?.deposit_amount ?? '0', status: 'received', received_at: c?.start_date ?? '', returned_at: null } })
  }),
  http.post(`${BASE}/contracts`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, contract_number: 'CNT-2025-099', status: 'draft', ...body }, { status: 201 })
  }),
  http.post(`${BASE}/contracts/:id/send-for-signature`, ({ params }) => {
    return HttpResponse.json({ contract: { ...contracts[0], id: Number(params.id), status: 'pending_signature' }, esign_provider_id: 'mock-123', sign_url: 'https://example.com/sign/mock' })
  }),
  http.post(`${BASE}/contracts/:id/activate`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/contracts/:id/terminate`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/contracts/:id/renew`, () => HttpResponse.json({ new_contract: { id: 100, contract_number: 'CNT-2025-100' } })),
  http.get(`${BASE}/contracts/:id/deposit`, () => HttpResponse.json({ id: 1, amount: '178.00', status: 'received', received_at: '2024-01-30', returned_at: null })),
  http.post(`${BASE}/deposits/:id/return`, () => HttpResponse.json({ message: 'ok' })),

  // Applications
  http.get(`${BASE}/applications`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(applications, page))
  }),
  http.get(`${BASE}/applications/:id`, ({ params }) => {
    const a = applications.find(a => a.id === Number(params.id)) ?? applications[0]
    return HttpResponse.json(a)
  }),
  http.post(`${BASE}/applications`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, status: 'new', ...body, created_at: new Date().toISOString() }, { status: 201 })
  }),
  http.patch(`${BASE}/applications/:id/status`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...applications[0], status: body.status })
  }),
  http.post(`${BASE}/applications/:id/assign`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/applications/:id/convert`, () => HttpResponse.json({ contract: { id: 10, contract_number: 'CNT-2025-010' } })),

  // Waiting list
  http.get(`${BASE}/waiting-list`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(waitingList, page))
  }),
  http.post(`${BASE}/waiting-list`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body, created_at: new Date().toISOString() }, { status: 201 })
  }),
  http.delete(`${BASE}/waiting-list/:id`, () => new HttpResponse(null, { status: 204 })),

  // Invoices
  http.get(`${BASE}/invoices`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    const perPage = Number(url.searchParams.get('per_page') ?? 20)
    return HttpResponse.json(paginate(invoices, page, perPage))
  }),
  http.get(`${BASE}/invoices/:id`, ({ params }) => {
    const inv = invoices.find(i => i.id === Number(params.id)) ?? invoices[0]!
    return HttpResponse.json({ ...inv, items: [{ description: 'Miete', amount: inv?.total_amount ?? '0', quantity: 1 }], payments: [] })
  }),
  http.post(`${BASE}/invoices`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, invoice_number: 'INV-2025-0099', status: 'draft', ...body }, { status: 201 })
  }),
  http.post(`${BASE}/invoices/:id/pay`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/invoices/:id/cancel`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/invoices/:id/remind`, () => HttpResponse.json({ message: 'ok' })),
  http.get(`${BASE}/invoices/datev-export`, () => new HttpResponse(new Blob(['mock,csv,data']), { headers: { 'Content-Type': 'text/csv' } })),

  // Payments
  http.get(`${BASE}/payments`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate([
      { id: 1, invoice: invoices[0], amount: '89.00', method: 'sepa', paid_at: '2025-02-01T10:00:00Z', reference: 'PAY-001' },
    ], page))
  }),

  // Dunning
  http.get(`${BASE}/debtors`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate([
      { id: 1, customer: customers[2], total_owed: '267.50', dunning_level: 2, days_overdue: 45, last_invoice: invoices[1] },
    ], page))
  }),
  http.post(`${BASE}/debtors/:id/escalate`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/debtors/:id/resolve`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/debtors/:id/pause`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/debtors/:id/notify`, () => HttpResponse.json({ message: 'ok' })),
  http.get(`${BASE}/debtors/:id/invoice`, () => HttpResponse.json(invoices[1])),

  // Deposits
  http.get(`${BASE}/deposits`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate([
      { id: 1, contract: contracts[0], amount: '178.00', status: 'received', received_at: '2024-01-30', returned_at: null },
      { id: 2, contract: contracts[1], amount: '298.00', status: 'received', received_at: '2024-02-25', returned_at: null },
    ], page))
  }),

  // Discount rules
  http.get(`${BASE}/discount-rules`, () => HttpResponse.json(paginate(discountRules))),
  http.post(`${BASE}/discount-rules`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body }, { status: 201 })
  }),
  http.put(`${BASE}/discount-rules/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const rule = discountRules.find(r => r.id === Number(params.id)) ?? discountRules[0]
    return HttpResponse.json({ ...rule, ...body })
  }),
  http.delete(`${BASE}/discount-rules/:id`, () => new HttpResponse(null, { status: 204 })),

  // Revenue targets
  http.get(`${BASE}/revenue-targets`, () => HttpResponse.json(revenueTargets)),
  http.post(`${BASE}/revenue-targets`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body }, { status: 201 })
  }),
  http.put(`${BASE}/revenue-targets/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const rt = revenueTargets.find(r => r.id === Number(params.id)) ?? revenueTargets[0]
    return HttpResponse.json({ ...rt, ...body })
  }),

  // Damage reports
  http.get(`${BASE}/damage-reports`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(damageReports, page))
  }),
  http.get(`${BASE}/damage-reports/:id`, ({ params }) => {
    const dr = damageReports.find(d => d.id === Number(params.id)) ?? damageReports[0]
    return HttpResponse.json(dr)
  }),
  http.post(`${BASE}/damage-reports`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, status: 'open', ...body }, { status: 201 })
  }),
  http.patch(`${BASE}/damage-reports/:id/status`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/damage-reports/:id/assign-vendor`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/damage-reports/:id/photos`, () => HttpResponse.json({ id: 1, path: '/photos/mock.jpg' })),

  // Tasks
  http.get(`${BASE}/tasks`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(tasks, page))
  }),
  http.get(`${BASE}/tasks/dashboard`, () => HttpResponse.json({ todo: tasks.filter(t => t.status === 'todo'), in_progress: tasks.filter(t => t.status === 'in_progress'), done: tasks.filter(t => t.status === 'done') })),
  http.get(`${BASE}/tasks/calendar`, () => HttpResponse.json({ data: tasks.filter(t => t.due_date) })),
  http.post(`${BASE}/tasks`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body, created_at: new Date().toISOString() }, { status: 201 })
  }),
  http.put(`${BASE}/tasks/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const task = tasks.find(t => t.id === Number(params.id)) ?? tasks[0]
    return HttpResponse.json({ ...task, ...body })
  }),
  http.delete(`${BASE}/tasks/:id`, () => new HttpResponse(null, { status: 204 })),

  // Vendors
  http.get(`${BASE}/vendors`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(vendors, page))
  }),
  http.get(`${BASE}/vendors/:id`, ({ params }) => {
    const v = vendors.find(v => v.id === Number(params.id)) ?? vendors[0]
    return HttpResponse.json(v)
  }),
  http.post(`${BASE}/vendors`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, active: true, rating: null, ...body }, { status: 201 })
  }),
  http.put(`${BASE}/vendors/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const v = vendors.find(v => v.id === Number(params.id)) ?? vendors[0]
    return HttpResponse.json({ ...v, ...body })
  }),
  http.delete(`${BASE}/vendors/:id`, () => new HttpResponse(null, { status: 204 })),

  // Electricity
  http.get(`${BASE}/electricity-pricing`, () => HttpResponse.json(electricityPricing)),
  http.post(`${BASE}/electricity-pricing`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, park: parks[0], ...body }, { status: 201 })
  }),
  http.put(`${BASE}/electricity-pricing/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const ep = electricityPricing.find(e => e.id === Number(params.id)) ?? electricityPricing[0]
    return HttpResponse.json({ ...ep, ...body })
  }),
  http.get(`${BASE}/units/:id/meters`, () => HttpResponse.json([])),
  http.post(`${BASE}/units/:id/meters`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body }, { status: 201 })
  }),

  // Mail
  http.get(`${BASE}/mail/sent`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate([
      { id: 1, to: customers[0]!.email, subject: 'Vertragsbestätigung', status: 'sent', sent_at: '2025-01-28T14:00:00Z', template: mailTemplates[0] },
      { id: 2, to: customers[1]!.email, subject: 'Zahlungserinnerung', status: 'sent', sent_at: '2025-02-15T09:00:00Z', template: mailTemplates[1] },
    ], page))
  }),
  http.get(`${BASE}/mail-templates`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(mailTemplates, page))
  }),
  http.get(`${BASE}/mail-templates/:id`, ({ params }) => {
    const mt = mailTemplates.find(m => m.id === Number(params.id)) ?? mailTemplates[0]
    return HttpResponse.json(mt)
  }),
  http.post(`${BASE}/mail-templates`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, active: true, variables: [], ...body }, { status: 201 })
  }),
  http.put(`${BASE}/mail-templates/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const mt = mailTemplates.find(m => m.id === Number(params.id)) ?? mailTemplates[0]
    return HttpResponse.json({ ...mt, ...body })
  }),
  http.delete(`${BASE}/mail-templates/:id`, () => new HttpResponse(null, { status: 204 })),
  http.post(`${BASE}/mail/mass-send`, () => HttpResponse.json({ message: 'Mails werden versendet', count: 5 })),
  http.post(`${BASE}/mail/schedule`, () => HttpResponse.json({ message: 'ok' })),
  http.get(`${BASE}/mail/recipient-count`, () => HttpResponse.json({ count: 42 })),

  // Document templates
  http.get(`${BASE}/document-templates`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(documentTemplates, page))
  }),
  http.get(`${BASE}/document-templates/:id`, ({ params }) => {
    const dt = documentTemplates.find(d => d.id === Number(params.id)) ?? documentTemplates[0]
    return HttpResponse.json(dt)
  }),
  http.post(`${BASE}/document-templates`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, active: true, variables: [], ...body }, { status: 201 })
  }),
  http.put(`${BASE}/document-templates/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const dt = documentTemplates.find(d => d.id === Number(params.id)) ?? documentTemplates[0]
    return HttpResponse.json({ ...dt, ...body })
  }),
  http.delete(`${BASE}/document-templates/:id`, () => new HttpResponse(null, { status: 204 })),
  http.post(`${BASE}/document-templates/:id/documents`, () => HttpResponse.json({ id: 1, path: '/docs/mock.pdf', filename: 'contract.pdf' })),

  // System settings
  http.get(`${BASE}/system-settings`, () => HttpResponse.json(systemSettings)),
  http.put(`${BASE}/system-settings`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...systemSettings, ...body })
  }),

  // Reference data
  http.get(`${BASE}/insurance-options`, () => HttpResponse.json(referenceData.insurance_options)),
  http.get(`${BASE}/insurance-options/:id`, ({ params }) => {
    const opt = referenceData.insurance_options.find(o => o.id === Number(params.id)) ?? referenceData.insurance_options[0]
    return HttpResponse.json(opt)
  }),
  http.post(`${BASE}/insurance-options`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, ...body }, { status: 201 })
  }),
  http.put(`${BASE}/insurance-options/:id`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 1, ...body })
  }),
  http.delete(`${BASE}/insurance-options/:id`, () => new HttpResponse(null, { status: 204 })),

  // Notifications
  http.get(`${BASE}/notifications`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json({ ...paginate(notifications, page), unread_count: 2 })
  }),
  http.post(`${BASE}/notifications/:id/read`, () => HttpResponse.json({ message: 'ok' })),
  http.post(`${BASE}/notifications/read-all`, () => HttpResponse.json({ message: 'ok' })),

  // Reports
  http.get(`${BASE}/reports/contracts`, () => new HttpResponse(new Blob(['mock,excel,data']), { headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' } })),
  http.get(`${BASE}/reports/customers`, () => new HttpResponse(new Blob(['mock,excel,data']), { headers: { 'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' } })),
  http.get(`${BASE}/reports/customers/detail`, () => HttpResponse.json({ data: customers, total: customers.length, last_page: 1 })),
  http.get(`${BASE}/reports/units`, () => HttpResponse.json({ data: units, total: units.length, last_page: 1 })),
  http.get(`${BASE}/reports/units/detail`, () => HttpResponse.json({ data: units, total: units.length, last_page: 1 })),
  http.get(`${BASE}/reports/finance`, () => HttpResponse.json({ total_revenue: 13073.50, total_invoiced: 13073.50, total_paid: 89.00, total_overdue: 149.00, by_park: [{ park: parks[0], revenue: 4823.50 }, { park: parks[1], revenue: 8250.00 }] })),
  http.get(`${BASE}/reports/finance/revenue`, () => HttpResponse.json({ data: revenueTargets, total: revenueTargets.length })),
  http.get(`${BASE}/reports/finance/debtors`, () => HttpResponse.json({ data: [{ customer: customers[2], amount: 267.50, days_overdue: 45 }], total: 1 })),
  http.get(`${BASE}/reports/finance/payments`, () => HttpResponse.json({ data: [], total: 0 })),
  http.get(`${BASE}/reports/finance/vendors`, () => HttpResponse.json({ data: vendors, total: vendors.length })),
  http.get(`${BASE}/reports/waiting-list`, () => HttpResponse.json({ data: waitingList, total: waitingList.length })),
  http.get(`${BASE}/reports/applications`, () => HttpResponse.json({ data: applications, total: applications.length, by_status: { new: 1, in_review: 1, approved: 1, rejected: 0 } })),
  http.get(`${BASE}/application-reports`, () => HttpResponse.json({ data: applications, total: applications.length })),

  // Admin Users
  http.get(`${BASE}/admin/users`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(users, page))
  }),
  http.post(`${BASE}/admin/users`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, active: true, parks: [], ...body }, { status: 201 })
  }),
  http.put(`${BASE}/admin/users/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const u = users.find(u => u.id === Number(params.id)) ?? users[0]
    return HttpResponse.json({ ...u, ...body })
  }),
  http.delete(`${BASE}/admin/users/:id`, () => new HttpResponse(null, { status: 204 })),
  http.get(`${BASE}/admin/employees`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate(users.filter(u => u.role !== 'admin'), page))
  }),
  http.post(`${BASE}/admin/employees`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ id: 99, active: true, parks: [], ...body }, { status: 201 })
  }),
  http.put(`${BASE}/admin/employees/:id`, async ({ request, params }) => {
    const body = await request.json() as Record<string, unknown>
    const u = users.find(u => u.id === Number(params.id)) ?? users[1]
    return HttpResponse.json({ ...u, ...body })
  }),
  http.delete(`${BASE}/admin/employees/:id`, () => new HttpResponse(null, { status: 204 })),

  // Audit log
  http.get(`${BASE}/audit-logs`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate([
      { id: 1, user: users[0], action: 'created', model: 'Contract', model_id: 1, created_at: '2025-02-01T10:00:00Z', changes: {} },
      { id: 2, user: users[1], action: 'updated', model: 'Unit', model_id: 3, created_at: '2025-02-10T14:00:00Z', changes: { status: ['free', 'rented'] } },
    ], page))
  }),
  http.get(`${BASE}/customers/:id/audit-log`, () => HttpResponse.json([])),
  http.get(`${BASE}/contracts/:id/audit-log`, () => HttpResponse.json([])),

  // Access codes
  http.get(`${BASE}/access-codes`, ({ request }) => {
    const url = new URL(request.url)
    const page = Number(url.searchParams.get('page') ?? 1)
    return HttpResponse.json(paginate([
      { id: 1, unit: units[0], code: '1234#', valid_from: '2024-02-01', valid_to: null, customer: customers[0] },
    ], page))
  }),
  http.post(`${BASE}/access-codes/sync`, () => HttpResponse.json({ message: 'Synchronisiert', synced: 1 })),

  // Search
  http.get(`${BASE}/search`, ({ request }) => {
    const url = new URL(request.url)
    const q = url.searchParams.get('q') ?? ''
    return HttpResponse.json({
      customers: customers.filter(c => (c.first_name + ' ' + c.last_name).toLowerCase().includes(q.toLowerCase())),
      contracts: contracts.filter(c => c.contract_number.includes(q)),
      units: units.filter(u => u.unit_number.includes(q)),
    })
  }),

  // Profile
  http.put(`${BASE}/profile`, async ({ request }) => {
    const body = await request.json() as Record<string, unknown>
    return HttpResponse.json({ ...users[0], ...body })
  }),

  // Customer reports (export)
  http.get(`${BASE}/customer-reports`, () => HttpResponse.json({ data: customers })),
]
