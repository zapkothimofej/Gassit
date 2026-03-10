<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppTable from '../../components/AppTable.vue'
import AppPagination from '../../components/AppPagination.vue'
import AppModal from '../../components/AppModal.vue'
import AppButton from '../../components/AppButton.vue'
import FormInput from '../../components/FormInput.vue'
import FormSelect from '../../components/FormSelect.vue'
import api from '../../api/axios'
import { fetchParks } from '../../api/parks'

interface Employee {
  id: number
  first_name: string
  last_name: string
  email: string
  phone: string
  role_title: string
  hire_date: string
  active: boolean
  park_id: number | null
  user_id: number | null
  park?: { id: number; name: string } | null
  user?: { id: number; name: string } | null
}

const employees = ref<Employee[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])
const users = ref<Array<{ id: number; name: string }>>([])

const filters = reactive({ page: 1 })

async function load() {
  loading.value = true
  try {
    const res = await api.get('/admin/employees', { params: { page: filters.page, per_page: 20 } })
    employees.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
  const ur = await api.get('/admin/users', { params: { per_page: 100 } })
  users.value = (ur.data.data ?? []).map((u: { id: number; name: string }) => ({ id: u.id, name: u.name }))
})

const columns = [
  { key: 'name', label: 'Name', sortable: false },
  { key: 'role_title', label: 'Role Title', sortable: false },
  { key: 'park', label: 'Park', sortable: false },
  { key: 'email', label: 'Email', sortable: false },
  { key: 'phone', label: 'Phone', sortable: false },
  { key: 'active', label: 'Active', sortable: false },
  { key: 'actions', label: 'Actions', sortable: false },
]

const showModal = ref(false)
const editingEmployee = ref<Employee | null>(null)
const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  role_title: '',
  hire_date: '',
  active: true,
  park_id: '' as string | number,
  user_id: '' as string | number,
})
const formError = ref('')
const saving = ref(false)

function openCreate() {
  editingEmployee.value = null
  form.first_name = ''
  form.last_name = ''
  form.email = ''
  form.phone = ''
  form.role_title = ''
  form.hire_date = ''
  form.active = true
  form.park_id = ''
  form.user_id = ''
  formError.value = ''
  showModal.value = true
}

function openEdit(emp: Employee) {
  editingEmployee.value = emp
  form.first_name = emp.first_name
  form.last_name = emp.last_name
  form.email = emp.email
  form.phone = emp.phone
  form.role_title = emp.role_title
  form.hire_date = emp.hire_date ?? ''
  form.active = emp.active
  form.park_id = emp.park_id ?? ''
  form.user_id = emp.user_id ?? ''
  formError.value = ''
  showModal.value = true
}

async function saveEmployee() {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      first_name: form.first_name,
      last_name: form.last_name,
      email: form.email,
      phone: form.phone,
      role_title: form.role_title,
      hire_date: form.hire_date || null,
      active: form.active,
      park_id: form.park_id || null,
      user_id: form.user_id || null,
    }
    if (editingEmployee.value) {
      await api.put(`/admin/employees/${editingEmployee.value.id}`, payload)
    } else {
      await api.post('/admin/employees', payload)
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

const showDeleteModal = ref(false)
const deletingEmployee = ref<Employee | null>(null)

function openDelete(emp: Employee) {
  deletingEmployee.value = emp
  showDeleteModal.value = true
}

async function confirmDelete() {
  if (!deletingEmployee.value) return
  try {
    await api.delete(`/admin/employees/${deletingEmployee.value.id}`)
    showDeleteModal.value = false
    load()
  } catch {
    alert('Delete failed')
  }
}

const parkOptions = ref<Array<{ value: string | number; label: string }>>([{ value: '', label: 'None' }])
const userOptions = ref<Array<{ value: string | number; label: string }>>([{ value: '', label: 'None' }])

onMounted(() => {
  // populated after parks/users load via watchers
})

import { watch } from 'vue'

watch(parks, (val) => {
  parkOptions.value = [{ value: '', label: 'None' }, ...val.map((p) => ({ value: p.id, label: p.name }))]
})

watch(users, (val) => {
  userOptions.value = [{ value: '', label: 'None' }, ...val.map((u) => ({ value: u.id, label: u.name }))]
})
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Employees</h1>
      <AppButton @click="openCreate">+ Create Employee</AppButton>
    </div>

    <AppTable
      :columns="columns"
      :rows="employees as unknown as Record<string, unknown>[]"
      :loading="loading"
    >
      <template #cell-name="{ row }">
        {{ (row as unknown as Employee).first_name }} {{ (row as unknown as Employee).last_name }}
      </template>
      <template #cell-park="{ row }">
        {{ (row as unknown as Employee).park?.name ?? '—' }}
      </template>
      <template #cell-active="{ row }">
        <span :class="(row as unknown as Employee).active ? 'text-green-600' : 'text-gray-400'">
          {{ (row as unknown as Employee).active ? 'Active' : 'Inactive' }}
        </span>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex gap-2">
          <AppButton size="sm" variant="secondary" @click="openEdit(row as unknown as Employee)">Edit</AppButton>
          <AppButton size="sm" variant="danger" @click="openDelete(row as unknown as Employee)">Delete</AppButton>
        </div>
      </template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p: number) => { filters.page = p; load() }"
    />

    <!-- Create/Edit Modal -->
    <AppModal v-model="showModal" :title="editingEmployee ? 'Edit Employee' : 'Create Employee'">
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <FormInput label="First Name" v-model="form.first_name" required />
          <FormInput label="Last Name" v-model="form.last_name" required />
        </div>
        <FormInput label="Email" v-model="form.email" type="email" required />
        <FormInput label="Phone" v-model="form.phone" />
        <FormInput label="Role Title" v-model="form.role_title" />
        <FormInput label="Hire Date" v-model="form.hire_date" type="date" />
        <FormSelect label="Park" v-model="form.park_id" :options="parkOptions" />
        <FormSelect label="Linked User Account" v-model="form.user_id" :options="userOptions" />
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" v-model="form.active" />
          Active
        </label>
        <p v-if="formError" class="text-red-500 text-sm">{{ formError }}</p>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="saving" @click="saveEmployee">Save</AppButton>
      </template>
    </AppModal>

    <!-- Delete Confirmation -->
    <AppModal v-model="showDeleteModal" title="Delete Employee">
      <p>Delete <strong>{{ deletingEmployee?.first_name }} {{ deletingEmployee?.last_name }}</strong>?</p>
      <template #footer>
        <AppButton variant="secondary" @click="showDeleteModal = false">Cancel</AppButton>
        <AppButton variant="danger" @click="confirmDelete">Delete</AppButton>
      </template>
    </AppModal>
  </div>
</template>
