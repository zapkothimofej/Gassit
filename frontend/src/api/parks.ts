import api from './axios'

export interface Park {
  id: number
  name: string
}

export interface UnitType {
  id: number
  name: string
  park_id: number
}

export function fetchParks() {
  return api.get<{ data: Park[] }>('/parks')
}

export function fetchUnitTypes(parkId?: number | null) {
  return api.get<{ data: UnitType[] }>(`/parks/${parkId}/unit-types`)
}

export function fetchUsers() {
  return api.get<{ data: Array<{ id: number; name: string; role: string }> }>('/admin/users')
}
