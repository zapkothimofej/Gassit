import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('../views/ForbiddenView.vue'),
    meta: { public: true },
  },
  {
    path: '/login/2fa',
    name: 'TwoFactor',
    component: () => import('../views/TwoFactorView.vue'),
    meta: { public: true },
  },
  {
    path: '/password-reset',
    name: 'PasswordReset',
    component: () => import('../views/PasswordResetView.vue'),
    meta: { public: true },
  },
  {
    path: '/password-reset/:token',
    name: 'PasswordResetConfirm',
    component: () => import('../views/PasswordResetConfirmView.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    component: () => import('../layouts/AppLayout.vue'),
    children: [
      {
        path: '',
        redirect: '/dashboard',
      },
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant', 'office_worker', 'park_worker', 'customer_service'] },
      },
      {
        path: 'parks',
        name: 'Parks',
        component: () => import('../views/ParksView.vue'),
        meta: { roles: ['admin', 'main_manager'] },
      },
      {
        path: 'units',
        name: 'Units',
        component: () => import('../views/UnitsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'units/:id',
        name: 'UnitDetail',
        component: () => import('../views/UnitDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'applications',
        name: 'Applications',
        component: () => import('../views/ApplicationsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'waiting-list',
        name: 'WaitingList',
        component: () => import('../views/WaitingListView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'applications/:id',
        name: 'ApplicationDetail',
        component: () => import('../views/ApplicationDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'contracts',
        name: 'Contracts',
        component: () => import('../views/ContractsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant'] },
      },
      {
        path: 'contracts/:id',
        name: 'ContractDetail',
        component: () => import('../views/ContractDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant'] },
      },
      {
        path: 'customers',
        name: 'Customers',
        component: () => import('../views/CustomersView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'customers/blacklist',
        name: 'Blacklist',
        component: () => import('../views/BlacklistView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'customers/:id',
        name: 'CustomerDetail',
        component: () => import('../views/CustomerDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'invoices',
        name: 'Invoices',
        component: () => import('../views/InvoicesView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'invoices/:id',
        name: 'InvoiceDetail',
        component: () => import('../views/InvoiceDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'deposits',
        name: 'Deposits',
        component: () => import('../views/DepositsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant', 'rental_manager'] },
      },
      {
        path: 'damage-reports',
        name: 'DamageReports',
        component: () => import('../views/DamageReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'damage-reports/:id',
        name: 'DamageReportDetail',
        component: () => import('../views/DamageReportDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'vendors',
        name: 'Vendors',
        component: () => import('../views/VendorsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager'] },
      },
      {
        path: 'payments',
        name: 'Payments',
        component: () => import('../views/PaymentsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'dunning',
        name: 'Dunning',
        component: () => import('../views/DunningView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'tasks',
        name: 'Tasks',
        component: () => import('../views/TasksView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker', 'office_worker'] },
      },
      {
        path: 'electricity',
        name: 'Electricity',
        component: () => import('../views/ElectricityView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'mail',
        name: 'Mail',
        component: () => import('../views/MailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'office_worker'] },
      },
      {
        path: 'reports',
        name: 'Reports',
        component: () => import('../views/ReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant', 'office_worker', 'customer_service'] },
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('../views/SettingsView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('../views/UsersView.vue'),
        meta: { roles: ['admin'] },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) return true

  if (!auth.isAuthenticated) {
    return { name: 'Login', query: { redirect: to.fullPath } }
  }

  if (!auth.user) {
    await auth.fetchUser()
  }

  const requiredRoles = to.meta.roles as string[] | undefined
  if (requiredRoles && auth.role && !requiredRoles.includes(auth.role)) {
    return { name: 'Forbidden' }
  }

  return true
})

export default router
