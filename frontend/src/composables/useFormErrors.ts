import { ref, type Ref } from 'vue'
import type { AxiosError } from 'axios'

interface ValidationErrors {
  [field: string]: string[]
}

interface ApiValidationError {
  message: string
  errors?: ValidationErrors
}

export function useFormErrors() {
  const errors: Ref<ValidationErrors> = ref({})
  const generalError = ref('')

  function setErrors(err: unknown): void {
    const axiosErr = err as AxiosError<ApiValidationError>
    if (axiosErr.response?.status === 422) {
      errors.value = axiosErr.response.data?.errors ?? {}
      generalError.value = axiosErr.response.data?.message ?? ''
    } else {
      errors.value = {}
      generalError.value = ''
    }
  }

  function clearErrors(): void {
    errors.value = {}
    generalError.value = ''
  }

  function fieldError(field: string): string {
    return errors.value[field]?.[0] ?? ''
  }

  return { errors, generalError, setErrors, clearErrors, fieldError }
}
