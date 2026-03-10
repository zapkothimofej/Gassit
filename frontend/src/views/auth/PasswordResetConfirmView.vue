<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api/axios'

const route = useRoute()
const token = route.params.token as string

const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const success = ref(false)
const error = ref('')
const loading = ref(false)

async function submit() {
  if (password.value !== passwordConfirm.value) {
    error.value = 'Passwords do not match'
    return
  }
  error.value = ''
  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      token,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirm.value,
    })
    success.value = true
  } catch {
    error.value = 'Password reset failed. The link may have expired.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>GASSIT</h1>
      <p class="subtitle">Set new password</p>

      <div v-if="success" class="success-msg">
        Password updated. <a href="/login">Sign in</a>
      </div>

      <form v-else @submit.prevent="submit">
        <p v-if="error" class="error">{{ error }}</p>
        <input v-model="email" type="email" placeholder="Email address" required class="field-input" />
        <input v-model="password" type="password" placeholder="New password" required minlength="8" class="field-input" />
        <input v-model="passwordConfirm" type="password" placeholder="Confirm password" required class="field-input" />
        <button type="submit" :disabled="loading">
          {{ loading ? 'Saving…' : 'Set password' }}
        </button>
      </form>

      <a href="/login" class="back-link">← Back to login</a>
    </div>
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

form { display: flex; flex-direction: column; gap: 0.75rem; }

.field-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.625rem 0.875rem;
  font-size: 0.9rem;
  width: 100%;
  box-sizing: border-box;
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

.success-msg {
  background: #dcfce7;
  color: #166534;
  padding: 0.875rem;
  border-radius: 8px;
  font-size: 0.875rem;
  text-align: center;
}

.error { color: #ef4444; font-size: 0.85rem; margin: 0; }
.back-link { font-size: 0.8rem; color: #3b82f6; text-align: center; text-decoration: none; }
</style>
