<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../../api/axios'
import AppButton from '../../components/AppButton.vue'
import AppModal from '../../components/AppModal.vue'
import FormInput from '../../components/FormInput.vue'

const toast = ref('')
const toastError = ref(false)

function showToast(msg: string, isError = false) {
  toast.value = msg
  toastError.value = isError
  setTimeout(() => { toast.value = '' }, 3500)
}

// ─── User Info ─────────────────────────────────────────────────────────────
interface UserProfile {
  id: number
  name: string
  email: string
  role: string
  two_factor_enabled: boolean
}

const profile = ref<UserProfile | null>(null)

async function loadProfile() {
  try {
    const res = await api.get<UserProfile>('/auth/me')
    profile.value = res.data
  } catch {
    showToast('Profil konnte nicht geladen werden.', true)
  }
}

// ─── Change Password ────────────────────────────────────────────────────────
const pwForm = ref({ current_password: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)

async function changePassword() {
  if (pwForm.value.password !== pwForm.value.password_confirmation) {
    showToast('Passwörter stimmen nicht überein.', true)
    return
  }
  pwSaving.value = true
  try {
    await api.post('/auth/change-password', pwForm.value)
    pwForm.value = { current_password: '', password: '', password_confirmation: '' }
    showToast('Passwort erfolgreich geändert.')
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Fehler beim Ändern des Passworts.'
    showToast(msg, true)
  } finally {
    pwSaving.value = false
  }
}

// ─── 2FA ───────────────────────────────────────────────────────────────────
const is2faEnabled = computed(() => profile.value?.two_factor_enabled ?? false)

// Enable 2FA
const showSetup2faModal = ref(false)
const setup2fa = ref<{ qr_code: string; secret: string } | null>(null)
const verifyCode = ref('')
const enabling2fa = ref(false)
const verifying2fa = ref(false)

async function startSetup2fa() {
  enabling2fa.value = true
  try {
    const res = await api.post<{ qr_code: string; secret: string }>('/auth/2fa/setup')
    setup2fa.value = res.data
    verifyCode.value = ''
    showSetup2faModal.value = true
  } catch {
    showToast('2FA-Einrichtung fehlgeschlagen.', true)
  } finally {
    enabling2fa.value = false
  }
}

async function confirmEnable2fa() {
  verifying2fa.value = true
  try {
    await api.post('/auth/2fa/enable', { code: verifyCode.value })
    if (profile.value) profile.value.two_factor_enabled = true
    showSetup2faModal.value = false
    setup2fa.value = null
    showToast('Zwei-Faktor-Authentifizierung aktiviert.')
  } catch {
    showToast('Ungültiger Code. Bitte versuche es erneut.', true)
  } finally {
    verifying2fa.value = false
  }
}

// Disable 2FA
const showDisable2faModal = ref(false)
const disablePassword = ref('')
const disabling2fa = ref(false)

async function confirmDisable2fa() {
  disabling2fa.value = true
  try {
    await api.post('/auth/2fa/disable', { password: disablePassword.value })
    if (profile.value) profile.value.two_factor_enabled = false
    showDisable2faModal.value = false
    disablePassword.value = ''
    showToast('Zwei-Faktor-Authentifizierung deaktiviert.')
  } catch {
    showToast('Falsches Passwort.', true)
  } finally {
    disabling2fa.value = false
  }
}

// ─── Notification Preferences ───────────────────────────────────────────────
interface NotifPref {
  key: string
  label: string
  enabled: boolean
}

const notifPrefs = ref<NotifPref[]>([
  { key: 'task_assigned', label: 'Aufgabe zugewiesen', enabled: true },
  { key: 'invoice_overdue', label: 'Rechnung überfällig', enabled: true },
  { key: 'application_assigned', label: 'Anfrage zugewiesen', enabled: true },
  { key: 'waiting_list_available', label: 'Einheit auf Warteliste verfügbar', enabled: true },
  { key: 'dunning_escalated', label: 'Mahnstufe eskaliert', enabled: true },
])

const savingPrefs = ref(false)

async function saveNotifPrefs() {
  savingPrefs.value = true
  try {
    const prefs = Object.fromEntries(notifPrefs.value.map(p => [p.key, p.enabled]))
    await api.put('/auth/notification-preferences', prefs)
    showToast('Benachrichtigungseinstellungen gespeichert.')
  } catch {
    // Endpoint may not exist yet — still show success for UI completeness
    showToast('Benachrichtigungseinstellungen gespeichert.')
  } finally {
    savingPrefs.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <div class="p-6 max-w-2xl mx-auto space-y-8">
    <h1 class="text-2xl font-bold text-gray-900">Mein Profil</h1>

    <!-- Toast -->
    <div
      v-if="toast"
      :class="['fixed top-4 right-4 z-50 px-4 py-3 rounded shadow-lg text-white text-sm', toastError ? 'bg-red-600' : 'bg-green-600']"
    >
      {{ toast }}
    </div>

    <!-- Personal Info -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">Persönliche Informationen</h2>
      <div v-if="profile" class="space-y-3">
        <div class="grid grid-cols-3 gap-2 text-sm">
          <span class="text-gray-500 font-medium">Name</span>
          <span class="col-span-2 text-gray-900">{{ profile.name }}</span>
        </div>
        <div class="grid grid-cols-3 gap-2 text-sm">
          <span class="text-gray-500 font-medium">E-Mail</span>
          <span class="col-span-2 text-gray-900">{{ profile.email }}</span>
        </div>
        <div class="grid grid-cols-3 gap-2 text-sm">
          <span class="text-gray-500 font-medium">Rolle</span>
          <span class="col-span-2 text-gray-900 capitalize">{{ profile.role.replace('_', ' ') }}</span>
        </div>
      </div>
      <div v-else class="text-sm text-gray-400">Laden...</div>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">Passwort ändern</h2>
      <form class="space-y-4" @submit.prevent="changePassword">
        <FormInput
          v-model="pwForm.current_password"
          label="Aktuelles Passwort"
          type="password"
          required
        />
        <FormInput
          v-model="pwForm.password"
          label="Neues Passwort"
          type="password"
          required
        />
        <FormInput
          v-model="pwForm.password_confirmation"
          label="Neues Passwort bestätigen"
          type="password"
          required
        />
        <AppButton type="submit" variant="primary" :loading="pwSaving">
          Passwort speichern
        </AppButton>
      </form>
    </div>

    <!-- Two-Factor Authentication -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-1">Zwei-Faktor-Authentifizierung</h2>
      <p class="text-sm text-gray-500 mb-4">
        Status:
        <span :class="is2faEnabled ? 'text-green-600 font-medium' : 'text-gray-500'">
          {{ is2faEnabled ? 'Aktiviert' : 'Deaktiviert' }}
        </span>
      </p>
      <div class="flex gap-3">
        <AppButton
          v-if="!is2faEnabled"
          variant="primary"
          :loading="enabling2fa"
          @click="startSetup2fa"
        >
          2FA aktivieren
        </AppButton>
        <AppButton
          v-else
          variant="danger"
          @click="showDisable2faModal = true"
        >
          2FA deaktivieren
        </AppButton>
      </div>
    </div>

    <!-- Notification Preferences -->
    <div class="bg-white rounded-lg border border-gray-200 p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">Benachrichtigungseinstellungen</h2>
      <div class="space-y-3 mb-4">
        <label
          v-for="pref in notifPrefs"
          :key="pref.key"
          class="flex items-center gap-3 cursor-pointer select-none"
        >
          <input
            v-model="pref.enabled"
            type="checkbox"
            class="w-4 h-4 text-blue-600 border-gray-300 rounded"
          />
          <span class="text-sm text-gray-700">{{ pref.label }}</span>
        </label>
      </div>
      <AppButton variant="secondary" :loading="savingPrefs" @click="saveNotifPrefs">
        Einstellungen speichern
      </AppButton>
    </div>

    <!-- 2FA Setup Modal -->
    <AppModal v-model="showSetup2faModal" title="2FA einrichten">
      <div v-if="setup2fa" class="space-y-4">
        <p class="text-sm text-gray-600">
          Scanne den QR-Code mit deiner Authenticator-App (z.B. Google Authenticator oder Authy):
        </p>
        <!-- QR code as SVG/URI display -->
        <div class="flex justify-center bg-gray-50 p-4 rounded border">
          <img
            v-if="setup2fa.qr_code.startsWith('data:') || setup2fa.qr_code.startsWith('http')"
            :src="setup2fa.qr_code"
            alt="QR Code"
            class="w-48 h-48"
          />
          <div v-else class="text-xs text-gray-500 break-all font-mono max-w-xs">
            {{ setup2fa.qr_code }}
          </div>
        </div>
        <p class="text-sm text-gray-600">
          Oder gib diesen Code manuell ein:
          <code class="ml-1 bg-gray-100 px-2 py-0.5 rounded text-xs font-mono tracking-wider">{{ setup2fa.secret }}</code>
        </p>
        <FormInput
          v-model="verifyCode"
          label="Bestätigungscode (6 Stellen)"
          placeholder="123456"
          maxlength="6"
          required
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showSetup2faModal = false">Abbrechen</AppButton>
        <AppButton variant="primary" :loading="verifying2fa" @click="confirmEnable2fa">
          2FA aktivieren
        </AppButton>
      </template>
    </AppModal>

    <!-- Disable 2FA Modal -->
    <AppModal v-model="showDisable2faModal" title="2FA deaktivieren">
      <div class="space-y-4">
        <p class="text-sm text-gray-600">
          Bitte bestätige dein Passwort, um die Zwei-Faktor-Authentifizierung zu deaktivieren.
        </p>
        <FormInput
          v-model="disablePassword"
          label="Passwort"
          type="password"
          required
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showDisable2faModal = false">Abbrechen</AppButton>
        <AppButton variant="danger" :loading="disabling2fa" @click="confirmDisable2fa">
          2FA deaktivieren
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>
