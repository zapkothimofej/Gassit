<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import StatusBadge from '../components/StatusBadge.vue'
import {
  fetchKpis, fetchMahnstuffe, fetchRevenue, fetchInvoices, fetchTasks, fetchCalendarEvents,
  type KpiData, type MahnstuffeRow, type RevenueRow, type Invoice, type Task,
} from '../api/dashboard'

const auth = useAuthStore()
const router = useRouter()

const selectedParkId = ref<number | null>(auth.parks[0]?.id ?? null)

const loading = ref(true)
const kpis = ref<KpiData | null>(null)
const mahnstuffe = ref<MahnstuffeRow[]>([])
const revenue = ref<RevenueRow[]>([])
const invoices = ref<Invoice[]>([])
const tasks = ref<Task[]>([])
const calendarTasks = ref<Task[]>([])

async function loadAll() {
  loading.value = true
  const pid = selectedParkId.value
  try {
    const [k, m, r, i, t, c] = await Promise.allSettled([
      fetchKpis(pid),
      fetchMahnstuffe(pid),
      fetchRevenue(pid),
      fetchInvoices(pid),
      fetchTasks(pid),
      fetchCalendarEvents(pid),
    ])
    if (k.status === 'fulfilled') kpis.value = k.value.data
    if (m.status === 'fulfilled') mahnstuffe.value = m.value.data
    if (r.status === 'fulfilled') revenue.value = r.value.data
    if (i.status === 'fulfilled') invoices.value = i.value.data.data ?? []
    if (t.status === 'fulfilled') {
      const raw = (t.value.data as unknown as Record<string, Task[]>)
      tasks.value = [
        ...(raw.todo ?? []),
        ...(raw.in_progress ?? []),
        ...(raw.done ?? []),
      ]
    }
    if (c.status === 'fulfilled') calendarTasks.value = (c.value.data as unknown as { data: Task[] }).data ?? []
  } finally {
    loading.value = false
  }
}

watch(selectedParkId, loadAll)
onMounted(loadAll)

const kpiCards = computed(() => {
  const k = kpis.value
  if (!k) return []
  return [
    { label: 'Anfragen',           value: k.new_requests,       link: '/applications' },
    { label: 'Neue Kunden',        value: k.new_customers,      link: '/customers' },
    { label: 'Neue Facturas',      value: k.new_invoices_count, link: '/invoices' },
    { label: 'Freie Einheiten',    value: k.free_units,         link: '/units' },
    { label: 'Im Gange',           value: k.ongoing_contracts,  link: '/contracts' },
    { label: 'Kuendigung',         value: k.cancellations,      link: '/contracts' },
    { label: 'Problem Clients',    value: k.problem_clients,    link: '/customers' },
    { label: 'Inaktive Einheiten', value: k.inactive_units,     link: '/units' },
    { label: 'Schuldner',          value: k.debtors_count,      link: '/dunning' },
    { label: 'Mahnstuffe',         value: k.max_dunning_level,  link: '/dunning' },
    { label: 'Mangel',             value: k.damages_open,       link: '/damage-reports' },
    { label: 'Schrauber',          value: k.repair_jobs_open,   link: '/damage-reports' },
  ]
})

const todoTasks   = computed(() => tasks.value.filter(t => t.status === 'todo').slice(0, 5))
const inProgTasks = computed(() => tasks.value.filter(t => t.status === 'in_progress').slice(0, 5))
const doneTasks   = computed(() => tasks.value.filter(t => t.status === 'done').slice(0, 5))

const calendarDays = computed(() => {
  const map: Record<string, Task[]> = {}
  for (const t of calendarTasks.value) {
    if (!t.due_date) continue
    const d = t.due_date.slice(0, 10)
    if (!map[d]) map[d] = []
    map[d].push(t)
  }
  const days: { date: string; tasks: Task[] }[] = []
  const now = new Date()
  for (let i = 0; i < 14; i++) {
    const d = new Date(now)
    d.setDate(d.getDate() + i)
    const key = d.toISOString().slice(0, 10)
    if (map[key]) days.push({ date: key, tasks: map[key] })
  }
  return days
})

function priorityColor(p: string) {
  return p === 'high' ? 'red' : p === 'medium' ? 'yellow' : 'gray'
}

function parkName(id: number) {
  return auth.parks.find(p => p.id === id)?.name ?? 'Park ' + id
}
</script>

<template>
  <div class="dashboard">
    <div class="page-header">
      <h2>Dashboard</h2>
      <select v-model="selectedParkId" class="park-sel">
        <option :value="null">All parks</option>
        <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <template v-if="loading">
      <div class="skeleton-grid">
        <div v-for="n in 12" :key="n" class="skeleton-card" />
      </div>
    </template>

    <template v-else>
      <div class="kpi-grid">
        <div
          v-for="card in kpiCards"
          :key="card.label"
          class="kpi-card"
          @click="router.push(card.link)"
        >
          <div class="kpi-value">{{ card.value }}</div>
          <div class="kpi-label">{{ card.label }}</div>
        </div>
      </div>

      <div class="row-2">
        <div class="widget">
          <h3>Mahnstuffe</h3>
          <table class="mini-table">
            <thead><tr><th>Kunde</th><th>Schulden</th><th>Stufe</th><th>Tage</th></tr></thead>
            <tbody>
              <tr v-for="row in mahnstuffe" :key="row.customer_id" class="clickable" @click="router.push('/customers')">
                <td>{{ row.customer_name }}</td>
                <td>{{ row.total_owed.toFixed(2) }}</td>
                <td><StatusBadge :status="String(row.dunning_level)" /></td>
                <td>{{ row.days_overdue }}d</td>
              </tr>
              <tr v-if="!mahnstuffe.length"><td colspan="4" class="empty">Keine Eintrage</td></tr>
            </tbody>
          </table>
        </div>

        <div class="widget">
          <h3>Umsatz</h3>
          <table class="mini-table">
            <thead><tr><th>Park</th><th>Soll</th><th>Ist</th><th>Delta</th></tr></thead>
            <tbody>
              <tr v-for="row in revenue" :key="row.park_id">
                <td>{{ parkName(row.park_id) }}</td>
                <td>{{ row.planned.toFixed(2) }}</td>
                <td>{{ row.actual.toFixed(2) }}</td>
                <td :class="row.actual >= row.planned ? 'pos' : 'neg'">
                  {{ row.actual >= row.planned ? '+' : '' }}{{ (row.actual - row.planned).toFixed(2) }}
                </td>
              </tr>
              <tr v-if="!revenue.length"><td colspan="4" class="empty">Keine Daten</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="row-2">
        <div class="widget">
          <h3>Neue Facturas</h3>
          <table class="mini-table">
            <thead><tr><th>Nummer</th><th>Kunde</th><th>Betrag</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="inv in invoices" :key="inv.id" class="clickable" @click="router.push('/invoices')">
                <td>{{ inv.invoice_number ?? ('#' + inv.id) }}</td>
                <td>{{ inv.customer ? (inv.customer.first_name + ' ' + inv.customer.last_name) : '-' }}</td>
                <td>{{ parseFloat(inv.total_amount).toFixed(2) }}</td>
                <td><StatusBadge :status="inv.status" /></td>
              </tr>
              <tr v-if="!invoices.length"><td colspan="4" class="empty">Keine Facturas</td></tr>
            </tbody>
          </table>
        </div>

        <div class="widget">
          <h3>Aufgaben</h3>
          <div class="kanban">
            <div class="kanban-col">
              <div class="kanban-header">To Do</div>
              <div v-for="t in todoTasks" :key="t.id" class="task-card">
                <span>{{ t.title }}</span>
                <span :class="['priority', priorityColor(t.priority)]">{{ t.priority }}</span>
              </div>
            </div>
            <div class="kanban-col">
              <div class="kanban-header">In Bearbeitung</div>
              <div v-for="t in inProgTasks" :key="t.id" class="task-card">
                <span>{{ t.title }}</span>
                <span :class="['priority', priorityColor(t.priority)]">{{ t.priority }}</span>
              </div>
            </div>
            <div class="kanban-col">
              <div class="kanban-header">Erledigt</div>
              <div v-for="t in doneTasks" :key="t.id" class="task-card">
                <span>{{ t.title }}</span>
                <span :class="['priority', priorityColor(t.priority)]">{{ t.priority }}</span>
              </div>
            </div>
          </div>
          <a href="/tasks" class="show-more">Alle Aufgaben ansehen</a>
        </div>
      </div>

      <div v-if="calendarDays.length" class="widget">
        <h3>Kalender (naechste 14 Tage)</h3>
        <div class="calendar">
          <div v-for="day in calendarDays" :key="day.date" class="cal-day">
            <div class="cal-date">{{ day.date }}</div>
            <div v-for="t in day.tasks" :key="t.id" class="cal-event" @click="router.push('/tasks')">
              {{ t.title }}
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dashboard { display: flex; flex-direction: column; gap: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.park-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.375rem 0.75rem; font-size: 0.875rem; }
.skeleton-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }
.skeleton-card { height: 90px; background: linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 10px; }
@keyframes shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }
.kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; cursor: pointer; transition: box-shadow 0.15s; }
.kpi-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.kpi-value { font-size: 2rem; font-weight: 700; color: #1e293b; }
.kpi-label { font-size: 0.8rem; color: #64748b; margin-top: 0.25rem; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.widget { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
.widget h3 { margin: 0 0 1rem; font-size: 1rem; color: #1e293b; }
.mini-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.mini-table th { text-align: left; color: #64748b; font-weight: 600; padding: 0.4rem 0.5rem; border-bottom: 1px solid #f1f5f9; }
.mini-table td { padding: 0.5rem 0.5rem; border-bottom: 1px solid #f8fafc; color: #374151; }
.clickable { cursor: pointer; }
.clickable:hover { background: #f8fafc; }
.empty { text-align: center; color: #94a3b8; padding: 1rem; }
.pos { color: #16a34a; font-weight: 600; }
.neg { color: #dc2626; font-weight: 600; }
.kanban { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; }
.kanban-col { display: flex; flex-direction: column; gap: 0.5rem; }
.kanban-header { font-size: 0.8rem; font-weight: 600; color: #64748b; padding-bottom: 0.4rem; border-bottom: 2px solid #e2e8f0; }
.task-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.5rem 0.625rem; font-size: 0.8rem; display: flex; justify-content: space-between; align-items: flex-start; gap: 0.25rem; }
.priority { font-size: 0.7rem; padding: 0.1rem 0.4rem; border-radius: 4px; text-transform: capitalize; white-space: nowrap; }
.priority.red { background: #fee2e2; color: #991b1b; }
.priority.yellow { background: #fef9c3; color: #854d0e; }
.priority.gray { background: #f1f5f9; color: #475569; }
.show-more { font-size: 0.75rem; color: #3b82f6; text-decoration: none; display: block; margin-top: 0.5rem; }
.calendar { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.cal-day { min-width: 120px; background: #f8fafc; border-radius: 8px; padding: 0.625rem; }
.cal-date { font-size: 0.75rem; font-weight: 600; color: #64748b; margin-bottom: 0.375rem; }
.cal-event { font-size: 0.78rem; background: #dbeafe; color: #1e40af; border-radius: 4px; padding: 0.2rem 0.4rem; margin-top: 0.2rem; cursor: pointer; }
.cal-event:hover { background: #bfdbfe; }

@media (max-width: 1023px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .skeleton-grid { grid-template-columns: repeat(2, 1fr); }
  .row-2 { grid-template-columns: 1fr; }
  .kanban { grid-template-columns: 1fr; }
}

@media (max-width: 639px) {
  .kpi-grid { grid-template-columns: 1fr; }
  .skeleton-grid { grid-template-columns: 1fr; }
}
</style>
