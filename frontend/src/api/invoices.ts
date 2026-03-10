import api from './axios'

export interface InvoiceItem {
  id: number
  description: string
  quantity: string
  unit_price: string
  total: string
  item_type: string
}

export interface InvoiceSummary {
  id: number
  invoice_number: string
  status: string
  issue_date: string
  due_date: string
  total_amount: string
  payment_method: string | null
  customer: { id: number; first_name: string; last_name: string; company_name: string | null; type: string }
  park: { id: number; name: string }
}

export interface InvoiceDetail extends InvoiceSummary {
  subtotal: string
  tax_rate: string
  tax_amount: string
  contract_id: number | null
  items: InvoiceItem[]
}

export interface InvoiceFilters {
  park_id?: number | null
  status?: string
  from?: string
  to?: string
  customer_id?: number | null
  contract_id?: number | null
  page?: number
  per_page?: number
}

export interface CreateInvoiceItem {
  description: string
  quantity: number
  unit_price: number
  item_type?: string
}

export function fetchInvoices(filters: InvoiceFilters = {}) {
  return api.get<{ data: InvoiceSummary[]; total: number; last_page: number }>('/invoices', {
    params: filters,
  })
}

export function fetchInvoice(id: number) {
  return api.get<InvoiceDetail>('/invoices/' + id)
}

export function createInvoice(data: {
  customer_id: number
  park_id: number
  contract_id?: number | null
  due_date: string
  tax_rate?: number
  items: CreateInvoiceItem[]
}) {
  return api.post<InvoiceDetail>('/invoices', data)
}

export function getInvoicePdfUrl(id: number) {
  return api.defaults.baseURL?.replace('/api', '') + '/api/invoices/' + id + '/pdf'
}

export function sendInvoice(id: number) {
  return api.post('/invoices/' + id + '/send')
}

export function cancelInvoice(id: number) {
  return api.post('/invoices/' + id + '/cancel')
}

export function createPaymentLink(invoiceId: number) {
  return api.post<{ payment_url: string; expires_at: string }>('/invoices/' + invoiceId + '/payment-link')
}

export function datevExport(params: { from: string; to: string; park_id?: number | null }) {
  return api.get('/invoices/datev-export', {
    params,
    responseType: 'blob',
  })
}

export function searchCustomers(search: string) {
  return api.get<{ data: Array<{ id: number; first_name: string; last_name: string; company_name: string | null; type: string }> }>(
    '/customers',
    { params: { search, per_page: 10 } },
  )
}
