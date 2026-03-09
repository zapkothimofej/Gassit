<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import AppTable from '../components/AppTable.vue'
import AppPagination from '../components/AppPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import AppModal from '../components/AppModal.vue'
import AppButton from '../components/AppButton.vue'
import FormInput from '../components/FormInput.vue'
import FormSelect from '../components/FormSelect.vue'
import api from '../api/axios'
import { fetchParks } from '../api/parks'

const auth = useAuthStore()

interface User {
  id: number
  name: string
  email: string
  role: string
  active: boolean
  parks: Array<{ id: number; name: string }>
  parks_count?: number
  last_login?: string | null
}

const users = ref<User[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const filters = reactive({ role: '', active: '', page: 1 })

const ROLE_OPTIONS = [
  { value: '', label: 'All Roles' },
  { value: 'admin', label: 'Admin' },
  { value: 'main_manager', label: 'Main Manager' },
  { value: 'rental_manager', label: 'Rental Manager' },
  { value: 'park_worker', label: 'Park Worker' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'office_worker', label: 'Office Worker' },
  { value: 'customer_service', label: 'Customer Service' },
]

const ACTIVE_OPTIONS = [
  { value: '', label: 'All' },
  { value: '1', label: 'Active' },
  { value: '0', label: 'Inactive' },
]

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: filters.page, per_page: 20 }
    if (filters.role) params.role = filters.role
    if (filters.active !== '') params.active = filters.active
    const res = await api.get('/admin/users', { params })
    users.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

const columns = [
  { key: 'name', label: 'Name', sortable: false },
  { key: 'email', label: 'Email', sortable: false },
  { key: 'role', label: 'Role', sortable: false },
  { key: 'parks', label: 'Parks', sortable: false },
  { key: 'active', label: 'Active', sortable: false },
  { key: 'actions', label: 'Actions', sortable: false },
]

// Create/Edit Modal
const showModal = ref(false)
const editingUser = ref<User | null>(null)
const form = reactive({
  name: '',
  email: '',
  role: 'rental_manager',
  active: true,
  park_ids: [] as number[],
})
const formError = ref('')
const saving = ref(false)

function openCreate() {
  editingUser.value = null
  form.name = ''
  form.email = ''
  form.role = 'rental_manager'
  form.active = true
  form.park_ids = []
  formError.value = ''
  showModal.value = true
}

function openEdit(user: User) {
  editingUser.value = user
  form.name = user.name
  form.email = user.email
  form.role = user.role
  form.active = user.active
  form.park_ids = (user.parks ?? []).map((p) => p.id)
  formError.value = ''
  showModal.value = true
}

async function saveUser() {
  saving.value = true
  formError.value = ''
  try {
    if (editingUser.value) {
      await api.put(`/admin/users/${editingUser.value.id}`, {
        name: form.name,
        email: form.email,
        role: form.role,
        active: form.active,
      })
      await api.post(`/admin/users/${editingUser.value.id}/parks`, { park_ids: form.park_ids })
    } else {
      const res = await api.post('/admin/users', {
        name: form.name,
        email: form.email,
        role: form.role,
        active: form.active,
        park_ids: form.park_ids,
      })
      await api.post(`/admin/users/${res.data.id}/parks`, { park_ids: form.park_ids })
    }
    showModal.value = false
    load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    formError.value = err?.response?.data?.message ?? 'Save failed'
  } finally {
    saving.value = false
  }
}

async function resetPassword(user: User) {
  try {
    await api.post('/auth/forgot-password', { email: user.email })
    alert('Password reset email sent')
  } catch {
    alert('Failed to send reset email')
  }
}

// Deactivate
const showDeactivateModal = ref(false)
const deactivatingUser = ref<User | null>(null)

function openDeactivate(user: User) {
  deactivatingUser.value = user
  showDeactivateModal.value = true
}

async function confirmDeactivate() {
  if (!deactivatingUser.value) return
  try {
    await api.delete(`/admin/users/${deactivatingUser.value.id}`)
    showDeactivateModal.value = false
    load()
  } catch {
    alert('Deactivation failed')
  }
}

function togglePark(id: number) {
  const idx = form.park_ids.indexOf(id)
  if (idx >= 0) form.park_ids.splice(idx, 1)
  else form.park_ids.push(id)
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Users</h1>
      <AppButton @click="openCreate">+ Create User</AppButton>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 mb-4 flex-wrap">
      <FormSelect
        label=""
        v-model="filters.role"
        :options="ROLE_OPTIONS"
        @change="() => { filters.page = 1; load() }"
      />
      <FormSelect
        label=""
        v-model="filters.active"
        :options="ACTIVE_OPTIONS"
        @change="() => { filters.page = 1; load() }"
      />
    </div>

    <AppTable
      :columns="columns"
      :rows="users as unknown as Record<string, unknown>[]"
      :loading="loading"
    >
      <template #cell-name="{ row }">
        <span class="font-medium">{{ (row as unknown as User).name }}</span>
      </template>
      <template #cell-role="{ row }">
        <StatusBadge :status="(row as unknown as User).role" />
      </template>
      <template #cell-parks="{ row }">
        {{ ((row as unknown as User).parks ?? []).length }}
      </template>
      <template #cell-active="{ row }">
        <span
          :class="(row as unknown as User).active ? 'text-green-600' : 'text-gray-400'"
        >{{ (row as unknown as User).active ? 'Active' : 'Inactive' }}</span>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex gap-2">
          <AppButton size="sm" variant="secondary" @click="openEdit(row as unknown as User)">Edit</AppButton>
          <AppButton
            size="sm"
            variant="danger"
            @click="openDeactivate(row as unknown as User)"
            v-if="(row as unknown as User).active && (row as unknown as User).id !== auth.user?.id"
          >Deactivate</AppButton>
        </div>
      </template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p: number) => { filters.page = p; load() }"
    />

    <!-- Create/Edit Modal -->
    <AppModal v-model="showModal" :title="editingUser ? 'Edit User' : 'Create User'">
      <div class="space-y-3">
        <FormInput label="Name" v-model="form.name" required />
        <FormInput label="Email" v-model="form.email" type="email" required />
        <FormSelect label="Role" v-model="form.role" :options="ROLE_OPTIONS.filter(o => o.value)" />
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Parks</label>
          <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto border rounded p-2">
            <label
              v-for="park in parks"
              :key="park.id"
              class="flex items-center gap-1 cursor-pointer text-sm"
            >
              <input
                type="checkbox"
                :checked="form.park_ids.includes(park.id)"
                @change="togglePark(park.id)"
              />
              {{ park.name }}
            </label>
          </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.active" />
          Active
        </label>
        <p v-if="formError" class="text-red-500 text-sm">{{ formError }}</p>
      </div>
      <template #footer>
        <div class="flex gap-2 justify-between w-full">
          <AppButton
            v-if="editingUser"
            variant="secondary"
            size="sm"
            @click="resetPassword(editingUser)"
          >Reset Password</AppButton>
          <div class="flex gap-2 ml-auto">
            <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
            <AppButton :loading="saving" @click="saveUser">Save</AppButton>
          </div>
        </div>
      </template>
    </AppModal>

    <!-- Deactivate Confirmation -->
    <AppModal v-model="showDeactivateModal" title="Deactivate User">
      <p>Deactivate <strong>{{ deactivatingUser?.name }}</strong>? They will lose access immediately.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showDeactivateModal = false">Cancel</AppButton>
        <AppButton variant="danger" @click="confirmDeactivate">Deactivate</AppButton>
      </template>
    </AppModal>
  </div>
</template>
