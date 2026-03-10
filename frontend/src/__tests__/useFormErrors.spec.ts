import { describe, it, expect } from 'vitest'
import { useFormErrors } from '../composables/useFormErrors'

describe('useFormErrors', () => {
  it('extracts field errors from 422 response', () => {
    const { errors, setErrors, fieldError } = useFormErrors()
    const err = { response: { status: 422, data: { message: 'Validation failed', errors: { email: ['Invalid email'] } } } }
    setErrors(err)
    expect(fieldError('email')).toBe('Invalid email')
    expect(errors.value.email).toEqual(['Invalid email'])
  })

  it('extracts generalError message from 422', () => {
    const { generalError, setErrors } = useFormErrors()
    const err = { response: { status: 422, data: { message: 'The given data was invalid.', errors: {} } } }
    setErrors(err)
    expect(generalError.value).toBe('The given data was invalid.')
  })

  it('clears errors on clearErrors()', () => {
    const { errors, generalError, setErrors, clearErrors } = useFormErrors()
    const err = { response: { status: 422, data: { message: 'Fail', errors: { name: ['Required'] } } } }
    setErrors(err)
    clearErrors()
    expect(errors.value).toEqual({})
    expect(generalError.value).toBe('')
  })

  it('clears state for non-422 errors', () => {
    const { errors, setErrors } = useFormErrors()
    setErrors({ response: { status: 500, data: {} } })
    expect(errors.value).toEqual({})
  })

  it('returns empty string for unknown field', () => {
    const { fieldError } = useFormErrors()
    expect(fieldError('nonexistent')).toBe('')
  })
})
