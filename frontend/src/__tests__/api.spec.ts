import { describe, it, expect, vi } from 'vitest'

vi.mock('../api/axios', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

import {
  fetchApplications,
  createApplication,
  searchCustomers,
} from '../api/applications'

import {
  fetchCustomers,
  createCustomer,
  exportCustomers,
} from '../api/customers'

import {
  fetchContracts,
  fetchContract,
  sendForSignature,
  activateContract,
  terminateContract,
  renewContract,
  fetchContractDeposit,
  returnDeposit,
  fetchInvoices,
  exportContracts,
} from '../api/contracts'

describe('applications API', () => {
  it('exports fetchApplications as a function', () => {
    expect(typeof fetchApplications).toBe('function')
  })

  it('exports createApplication as a function', () => {
    expect(typeof createApplication).toBe('function')
  })

  it('exports searchCustomers as a function', () => {
    expect(typeof searchCustomers).toBe('function')
  })
})

describe('customers API', () => {
  it('exports fetchCustomers as a function', () => {
    expect(typeof fetchCustomers).toBe('function')
  })

  it('exports createCustomer as a function', () => {
    expect(typeof createCustomer).toBe('function')
  })

  it('exports exportCustomers as a function', () => {
    expect(typeof exportCustomers).toBe('function')
  })
})

describe('contracts API', () => {
  it('exports fetchContracts as a function', () => {
    expect(typeof fetchContracts).toBe('function')
  })

  it('exports fetchContract as a function', () => {
    expect(typeof fetchContract).toBe('function')
  })

  it('exports sendForSignature as a function', () => {
    expect(typeof sendForSignature).toBe('function')
  })

  it('exports activateContract as a function', () => {
    expect(typeof activateContract).toBe('function')
  })

  it('exports terminateContract as a function', () => {
    expect(typeof terminateContract).toBe('function')
  })

  it('exports renewContract as a function', () => {
    expect(typeof renewContract).toBe('function')
  })

  it('exports fetchContractDeposit as a function', () => {
    expect(typeof fetchContractDeposit).toBe('function')
  })

  it('exports returnDeposit as a function', () => {
    expect(typeof returnDeposit).toBe('function')
  })

  it('exports fetchInvoices as a function', () => {
    expect(typeof fetchInvoices).toBe('function')
  })

  it('exports exportContracts as a function', () => {
    expect(typeof exportContracts).toBe('function')
  })
})
