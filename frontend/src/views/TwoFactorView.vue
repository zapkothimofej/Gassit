<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../api/axios'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const code = ref('')
const error = ref('')
const loading = ref(false)

const tempToken = route.query.temp_token as string

async function submit() {
  if (code.value.length !== 6) {
    error.value = 'Please enter 6-digit code'
    return
  }
  error.value = ''
  loading.value = true
  try {
    const response = await api.post('/auth/2fa/verify', {
      temp_token: tempToken,
      code: code.value,
    })
    auth.token = response.data.token
    localStorage.setItem('auth_token', response.data.token)
    auth.user = response.data.user
    router.push('/dashboard')
  } catch {
    error.value = 'Invalid code. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page">
    <form class="card" @submit.prevent="submit">
      <h1>GASSIT</h1>
      <p class="subtitle">Two-factor authentication</p>
      <p v-if="error" class="error">{{ error }}</p>
      <input
        v-model="code"
        type="text"
        inputmode="numeric"
        pattern="[0-9]{6}"
        maxlength="6"
        placeholder="6-digit code"
        required
        class="code-input"
      />
      <button type="submit" :disabled="loading">
        {{ loading ? 'Verifying…' : 'Verify' }}
      </button>
      <a href="/login" class="back-link">← Back to login</a>
    </form>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
}

.card {
  background: #fff;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.08);
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 320px;
}

h1 { margin: 0; font-size: 1.5rem; text-align: center; color: #1e293b; }
.subtitle { margin: 0; text-align: center; color: #64748b; font-size: 0.875rem; }

.code-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.75rem;
  font-size: 1.5rem;
  text-align: center;
  letter-spacing: 0.5rem;
}

button {
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 0.75rem;
  font-size: 0.9rem;
  cursor: pointer;
}
button:disabled { opacity: 0.6; cursor: not-allowed; }

.error { color: #ef4444; font-size: 0.85rem; margin: 0; }
.back-link { font-size: 0.8rem; color: #3b82f6; text-align: center; text-decoration: none; }
</style>
