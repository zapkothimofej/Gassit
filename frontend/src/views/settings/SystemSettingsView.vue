<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../../api/axios'

interface SystemSetting {
  id: number
  key: string
  value: string
  description: string | null
}

const loading = ref(false)
const saving = ref(false)
const toast = ref('')

const form = ref<Record<string, string>>({
  invoice_day: '1',
  tax_rate: '19',
  dunning_delay_1: '7',
  dunning_delay_2: '14',
  dunning_delay_3: '30',
  dunning_fee_1: '5',
  dunning_fee_2: '10',
  dunning_fee_3: '20',
  payment_retry_max: '3',
  payment_retry_interval_hours: '24',
  session_timeout_minutes: '60',
  login_max_attempts: '5',
  default_primary_color: '#3b82f6',
  default_language: 'de',
})

async function load() {
  loading.value = true
  try {
    const res = await api.get<SystemSetting[]>('/system-settings')
    for (const s of res.data) {
      if (s.key in form.value) {
        form.value[s.key] = s.value
      }
    }
  } catch {
    // keep defaults
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const settings = Object.entries(form.value).map(([key, value]) => ({ key, value }))
    await api.put('/system-settings', { settings })
    showToast('Settings saved successfully.')
  } catch {
    showToast('Failed to save settings.')
  } finally {
    saving.value = false
  }
}

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">System Settings</h1>
    </div>

    <div v-if="loading" class="loading">Loading…</div>

    <form v-else @submit.prevent="save">
      <!-- Invoice -->
      <div class="section">
        <h2 class="section-title">Invoice</h2>
        <div class="card">
          <div class="field">
            <label class="label">Invoice Day (1–28)</label>
            <input v-model="form.invoice_day" type="number" min="1" max="28" class="input" />
            <span class="hint">Day of month invoices are generated</span>
          </div>
          <div class="field">
            <label class="label">Default Tax Rate (%)</label>
            <input v-model="form.tax_rate" type="number" min="0" max="100" step="0.01" class="input" />
          </div>
        </div>
      </div>

      <!-- Dunning -->
      <div class="section">
        <h2 class="section-title">Dunning</h2>
        <div class="card">
          <div class="grid-3">
            <div class="field">
              <label class="label">Delay Level 1 (days)</label>
              <input v-model="form.dunning_delay_1" type="number" min="1" class="input" />
            </div>
            <div class="field">
              <label class="label">Delay Level 2 (days)</label>
              <input v-model="form.dunning_delay_2" type="number" min="1" class="input" />
            </div>
            <div class="field">
              <label class="label">Delay Level 3 (days)</label>
              <input v-model="form.dunning_delay_3" type="number" min="1" class="input" />
            </div>
          </div>
          <div class="grid-3">
            <div class="field">
              <label class="label">Fee Level 1 (€)</label>
              <input v-model="form.dunning_fee_1" type="number" min="0" step="0.01" class="input" />
            </div>
            <div class="field">
              <label class="label">Fee Level 2 (€)</label>
              <input v-model="form.dunning_fee_2" type="number" min="0" step="0.01" class="input" />
            </div>
            <div class="field">
              <label class="label">Fee Level 3 (€)</label>
              <input v-model="form.dunning_fee_3" type="number" min="0" step="0.01" class="input" />
            </div>
          </div>
        </div>
      </div>

      <!-- Payment -->
      <div class="section">
        <h2 class="section-title">Payment</h2>
        <div class="card">
          <div class="grid-2">
            <div class="field">
              <label class="label">Max Retry Attempts</label>
              <input v-model="form.payment_retry_max" type="number" min="0" class="input" />
            </div>
            <div class="field">
              <label class="label">Retry Interval (hours)</label>
              <input v-model="form.payment_retry_interval_hours" type="number" min="1" class="input" />
            </div>
          </div>
        </div>
      </div>

      <!-- Security -->
      <div class="section">
        <h2 class="section-title">Security</h2>
        <div class="card">
          <div class="grid-2">
            <div class="field">
              <label class="label">Session Timeout (minutes)</label>
              <input v-model="form.session_timeout_minutes" type="number" min="5" class="input" />
            </div>
            <div class="field">
              <label class="label">Max Failed Login Attempts</label>
              <input v-model="form.login_max_attempts" type="number" min="1" class="input" />
            </div>
          </div>
        </div>
      </div>

      <!-- Branding -->
      <div class="section">
        <h2 class="section-title">Branding</h2>
        <div class="card">
          <div class="grid-2">
            <div class="field">
              <label class="label">Default Primary Color</label>
              <div class="color-row">
                <input v-model="form.default_primary_color" type="color" class="color-input" />
                <input v-model="form.default_primary_color" type="text" class="input" placeholder="#3b82f6" />
              </div>
            </div>
            <div class="field">
              <label class="label">Default Language</label>
              <select v-model="form.default_language" class="input">
                <option value="de">Deutsch</option>
                <option value="en">English</option>
                <option value="ru">Русский</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="form-footer">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save Settings' }}
        </button>
      </div>
    </form>

    <div v-if="toast" class="toast">{{ toast }}</div>
  </div>
</template>

<style scoped>
.page { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; max-width: 860px; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.loading { text-align: center; color: #94a3b8; padding: 2rem; }

.section { display: flex; flex-direction: column; gap: 0.75rem; }
.section-title { font-size: 1rem; font-weight: 600; color: #334155; margin: 0; padding-bottom: 0.25rem; border-bottom: 2px solid #e2e8f0; }

.card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }

.field { display: flex; flex-direction: column; gap: 0.35rem; }
.label { font-size: 0.8rem; font-weight: 500; color: #475569; }
.hint { font-size: 0.75rem; color: #94a3b8; }

.input { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 0.875rem; color: #1e293b; background: #fff; outline: none; transition: border-color .15s; }
.input:focus { border-color: #3b82f6; }

.color-row { display: flex; gap: 0.5rem; align-items: center; }
.color-input { width: 40px; height: 38px; border: 1px solid #cbd5e1; border-radius: 7px; padding: 2px; cursor: pointer; }

.form-footer { display: flex; justify-content: flex-end; }
.btn-primary { background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: 0.6rem 1.5rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; }
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.toast { position: fixed; bottom: 1.5rem; right: 1.5rem; background: #1e293b; color: #fff; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.875rem; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,.2); }
</style>
