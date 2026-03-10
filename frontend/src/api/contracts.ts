import api from './axios'
import { get, post } from './client'

export interface ContractSignature {
  id: number
  signer_type: string
  signer_name: string
  signed_at: string
}

export interface ContractDetail {
  id: number
  contract_number: string
  status: string
  start_date: string
  end_date: string | null
  rent_amount: string
  deposit_amount: string
  insurance_amount: string
  notice_period_days: number
  notes: string | null
  signed_at: string | null
  terminated_at: string | null
  termination_notice_date: string | null
  customer: { id: number; first_name: string; last_name: string; company_name: string | null; type: string }
  unit: { id: number; unit_number: string; park_id: number }
  signatures: ContractSignature[]
}

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

export interface Deposit {
  id: number
  amount: string
  status: string
  received_at: string | null
  returned_at: string | null
}

export interface Invoice {
  id: number
  invoice_number: string
  status: string
  amount: string
  due_date: string
  issued_at: string
}

export function fetchContracts(filters: ContractFilters = {}) {
  return get<{ data: Contract[]; total: number; last_page: number }>('/contracts', { params: filters })
}

export function fetchContract(id: number) {
  return get<ContractDetail>('/contracts/' + id)
}

export function sendForSignature(id: number) {
  return post<{ contract: ContractDetail; esign_provider_id: string; sign_url: string }>(
    '/contracts/' + id + '/send-for-signature',
  )
}

export function activateContract(id: number) {
  return post('/contracts/' + id + '/activate')
}

export function terminateContract(
  id: number,
  data: { termination_type: string; termination_notice_date: string; termination_reason_id?: number | null },
) {
  return post('/contracts/' + id + '/terminate', data)
}

export function renewContract(id: number, data: { start_date: string; rent_amount: string }) {
  return post<{ new_contract: { id: number } }>('/contracts/' + id + '/renew', data)
}

export function fetchContractDeposit(contractId: number) {
  return get<Deposit>('/contracts/' + contractId + '/deposit')
}

export function returnDeposit(depositId: number, data: Record<string, unknown>) {
  return post('/deposits/' + depositId + '/return', data)
}

export function fetchInvoices(filters: { contract_id?: number; page?: number; per_page?: number } = {}) {
  return get<{ data: Invoice[]; total: number; last_page: number }>('/invoices', { params: filters })
}

export function exportContracts(filters: ContractFilters = {}) {
  return api.get('/reports/contracts', {
    params: { ...filters, format: 'xlsx' },
    responseType: 'blob',
  })
}
