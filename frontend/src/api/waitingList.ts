import api from './axios'

export interface WaitingListEntry {
  id: number
  customer?: { id: number; first_name: string; last_name: string; email: string }
  unit_type?: { id: number; name: string }
  park?: { id: number; name: string }
  priority_score: number
  notes: string | null
  notified_at: string | null
  created_at: string
}

export function fetchWaitingList(parkId?: number | null, unitTypeId?: number | null) {
  return api.get<{ data: WaitingListEntry[] }>('/waiting-list', {
    params: {
      park_id: parkId || undefined,
      unit_type_id: unitTypeId || undefined,
    },
  })
}

export function addToWaitingList(data: {
  customer_id: number
  unit_type_id: number
  park_id: number
  priority_score?: number
  notes?: string
}) {
  return api.post<WaitingListEntry>('/waiting-list', data)
}

export function updateWaitingListEntry(id: number, data: { priority_score?: number; notes?: string }) {
  return api.put('/waiting-list/' + id, data)
}

export function deleteWaitingListEntry(id: number) {
  return api.delete('/waiting-list/' + id)
}

export function notifyWaitingListEntry(id: number, unitId?: number) {
  return api.post('/waiting-list/' + id + '/notify', unitId ? { unit_id: unitId } : {})
}

export function convertWaitingListEntry(id: number) {
  return api.post('/waiting-list/' + id + '/convert')
}
