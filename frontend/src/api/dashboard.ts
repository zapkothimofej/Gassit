import api from './axios'

export interface KpiData {
  new_requests: number
  new_customers: number
  new_invoices_count: number
  free_units: number
  ongoing_contracts: number
  cancellations: number
  problem_clients: number
  inactive_units: number
  debtors_count: number
  max_dunning_level: number
  damages_open: number
  repair_jobs_open: number
}

export interface MahnstuffeRow {
  customer_id: number
  customer_name: string
  total_owed: number
  dunning_level: number
  days_overdue: number
}

export interface RevenueRow {
  park_id: number
  planned: number
  actual: number
}

export interface Invoice {
  id: number
  invoice_number: string
  customer?: { first_name: string; last_name: string }
  total_amount: string
  status: string
}

export interface Task {
  id: number
  title: string
  status: string
  priority: string
  due_date: string | null
}

export interface CalendarEvent {
  id: number | string
  title: string
  date: string
  type: string
  entityId: number
}

export function fetchKpis(parkId?: number | null) {
  return api.get<KpiData>('/dashboard/kpis', { params: parkId ? { park_id: parkId } : {} })
}

export function fetchMahnstuffe(parkId?: number | null) {
  return api.get<MahnstuffeRow[]>('/dashboard/mahnstuffe', { params: parkId ? { park_id: parkId } : {} })
}

export function fetchRevenue(parkId?: number | null) {
  return api.get<RevenueRow[]>('/dashboard/revenue', { params: parkId ? { park_id: parkId } : {} })
}

export function fetchInvoices(parkId?: number | null) {
  const params: Record<string, unknown> = { per_page: 10 }
  if (parkId) params.park_id = parkId
  return api.get<{ data: Invoice[] }>('/invoices', { params })
}

export function fetchTasks(parkId?: number | null) {
  return api.get<{ data: Task[] }>('/tasks/dashboard', { params: parkId ? { park_id: parkId } : {} })
}

export function fetchCalendarEvents(parkId?: number | null) {
  const now = new Date()
  const to = new Date(now)
  to.setDate(to.getDate() + 14)
  const fmt = (d: Date) => d.toISOString().slice(0, 10)
  const params: Record<string, unknown> = { from: fmt(now), to: fmt(to) }
  if (parkId) params.park_id = parkId
  return api.get<{ data: Task[] }>('/tasks/calendar', { params })
}
