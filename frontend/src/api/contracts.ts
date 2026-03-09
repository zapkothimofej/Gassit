import api from './axios'

export interface Contract {
  id: number
  contract_number: string
  status: string
  start_date: string
  end_date: string | null
  rent_amount: string
  customer: { id: number; first_name: string; last_name: string; company_name: string | null; type: string }
  unit: { id: number; unit_number: string }
  park: { id: number; name: string }
}

export interface ContractFilters {
  park_id?: number | null
  status?: string
  start_date_from?: string
  start_date_to?: string
  search?: string
  page?: number
  per_page?: number
}

export function fetchContracts(filters: ContractFilters = {}) {
  return api.get<{ data: Contract[]; total: number; last_page: number }>('/contracts', {
    params: filters,
  })
}

export function exportContracts(filters: ContractFilters = {}) {
  return api.get('/reports/contracts', {
    params: { ...filters, format: 'xlsx' },
    responseType: 'blob',
  })
}
