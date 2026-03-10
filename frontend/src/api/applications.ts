import api from './axios'

export interface Application {
  id: number
  customer?: { id: number; first_name: string; last_name: string; email: string }
  unit_type?: { id: number; name: string }
  park?: { id: number; name: string }
  status: string
  assigned_to?: { id: number; name: string }
  source: string
  notes?: string
  desired_start_date?: string
  created_at: string
}

export interface ApplicationFilters {
  park_id?: number | null
  status?: string[]
  assigned_to?: number | null
  from?: string
  to?: string
  search?: string
  page?: number
  per_page?: number
}

export function fetchApplications(filters: ApplicationFilters = {}) {
  return api.get<{ data: Application[]; total: number; last_page: number }>('/applications', {
    params: {
      ...filters,
      status: filters.status?.join(',') || undefined,
    },
  })
}

export function createApplication(data: {
  customer_id: number
  unit_type_id: number
  park_id: number
  desired_start_date?: string
  notes?: string
  source: string
}) {
  return api.post<Application>('/applications', data)
}

export function searchCustomers(q: string) {
  return api.get<{ data: Array<{ id: number; first_name: string; last_name: string; email: string }> }>(
    '/customers',
    { params: { search: q, per_page: 10 } },
  )
}
