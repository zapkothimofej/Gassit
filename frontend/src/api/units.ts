import api from './axios'

export interface Unit {
  id: number
  unit_number: string
  status: string
  size_m2: number | null
  rent_amount: string
  building: string | null
  floor: number | null
  park_id: number
  unit_type?: { id: number; name: string }
  current_tenant?: { id: number; first_name: string; last_name: string }
}

export interface UnitFilters {
  park_id?: number | null
  unit_type_id?: number | null
  status?: string
  page?: number
  per_page?: number
}

export function fetchUnits(parkId: number, filters: UnitFilters = {}) {
  return api.get<{ data: Unit[]; total: number; last_page: number }>('/parks/' + parkId + '/units', {
    params: filters,
  })
}

export function createUnit(parkId: number, data: Record<string, unknown>) {
  return api.post<Unit>('/parks/' + parkId + '/units', data)
}

export function updateUnitStatus(unitId: number, status: string) {
  return api.put('/units/' + unitId + '/status', { status })
}
