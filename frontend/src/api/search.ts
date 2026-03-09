import api from './axios'

export interface SearchResultItem {
  id: number
  label: string
  subtitle?: string
}

export interface SearchResults {
  customers: SearchResultItem[]
  units: SearchResultItem[]
  applications: SearchResultItem[]
  contracts: SearchResultItem[]
  invoices: SearchResultItem[]
}

export function globalSearch(q: string) {
  return api.get<SearchResults>('/search', { params: { q } })
}
