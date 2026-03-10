import api from './axios'
import { get, post } from './client'

export interface Customer {
  id: number
  type: 'private' | 'company'
  status: string
  first_name: string
  last_name: string
  company_name: string | null
  email: string
  phone: string
  id_number: string | null
  park?: { id: number; name: string }
  created_at: string
}

export interface CustomerFilters {
  search?: string
  park_id?: number | null
  status?: string
  type?: string
  page?: number
  per_page?: number
}

export function fetchCustomers(filters: CustomerFilters = {}) {
  return get<{ data: Customer[]; total: number; last_page: number }>('/customers', { params: filters })
}

export function createCustomer(data: Record<string, unknown>) {
  return post<Customer>('/customers', data)
}

export function exportCustomers(filters: CustomerFilters = {}) {
  return api.get('/reports/customers', {
    params: { ...filters, format: 'xlsx' },
    responseType: 'blob',
  })
}
