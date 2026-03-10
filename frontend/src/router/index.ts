import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/auth/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('../views/auth/ForbiddenView.vue'),
    meta: { public: true },
  },
  {
    path: '/login/2fa',
    name: 'TwoFactor',
    component: () => import('../views/auth/TwoFactorView.vue'),
    meta: { public: true },
  },
  {
    path: '/password-reset',
    name: 'PasswordReset',
    component: () => import('../views/auth/PasswordResetView.vue'),
    meta: { public: true },
  },
  {
    path: '/password-reset/:token',
    name: 'PasswordResetConfirm',
    component: () => import('../views/auth/PasswordResetConfirmView.vue'),
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
        component: () => import('../views/dashboard/DashboardView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant', 'office_worker', 'park_worker', 'customer_service'] },
      },
      {
        path: 'parks',
        name: 'Parks',
        component: () => import('../views/admin/ParksView.vue'),
        meta: { roles: ['admin', 'main_manager'] },
      },
      {
        path: 'unit-types',
        name: 'UnitTypes',
        component: () => import('../views/units/UnitTypesView.vue'),
        meta: { roles: ['admin', 'main_manager'] },
      },
      {
        path: 'discount-rules',
        name: 'DiscountRules',
        component: () => import('../views/finance/DiscountRulesView.vue'),
        meta: { roles: ['admin', 'main_manager'] },
      },
      {
        path: 'revenue-targets',
        name: 'RevenueTargets',
        component: () => import('../views/finance/RevenueTargetsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'units',
        name: 'Units',
        component: () => import('../views/units/UnitsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'units/reports',
        name: 'UnitReports',
        component: () => import('../views/units/UnitReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'units/:id',
        name: 'UnitDetail',
        component: () => import('../views/units/UnitDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'applications',
        name: 'Applications',
        component: () => import('../views/applications/ApplicationsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'applications/reports',
        name: 'ApplicationReports',
        component: () => import('../views/applications/ApplicationReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'waiting-list',
        name: 'WaitingList',
        component: () => import('../views/applications/WaitingListView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'applications/:id',
        name: 'ApplicationDetail',
        component: () => import('../views/applications/ApplicationDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'contracts',
        name: 'Contracts',
        component: () => import('../views/contracts/ContractsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant'] },
      },
      {
        path: 'contracts/:id',
        name: 'ContractDetail',
        component: () => import('../views/contracts/ContractDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant'] },
      },
      {
        path: 'customers',
        name: 'Customers',
        component: () => import('../views/customers/CustomersView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'customers/reports',
        name: 'CustomerReports',
        component: () => import('../views/customers/CustomerReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'customers/blacklist',
        name: 'Blacklist',
        component: () => import('../views/customers/BlacklistView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'customers/:id',
        name: 'CustomerDetail',
        component: () => import('../views/customers/CustomerDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'office_worker', 'customer_service'] },
      },
      {
        path: 'finance/reports',
        name: 'FinanceReports',
        component: () => import('../views/finance/FinanceReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'invoices',
        name: 'Invoices',
        component: () => import('../views/finance/InvoicesView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'invoices/:id',
        name: 'InvoiceDetail',
        component: () => import('../views/finance/InvoiceDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'deposits',
        name: 'Deposits',
        component: () => import('../views/finance/DepositsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant', 'rental_manager'] },
      },
      {
        path: 'damage-reports',
        name: 'DamageReports',
        component: () => import('../views/operations/DamageReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'damage-reports/:id',
        name: 'DamageReportDetail',
        component: () => import('../views/operations/DamageReportDetailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'vendors',
        name: 'Vendors',
        component: () => import('../views/operations/VendorsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager'] },
      },
      {
        path: 'payments',
        name: 'Payments',
        component: () => import('../views/finance/PaymentsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'dunning',
        name: 'Dunning',
        component: () => import('../views/finance/DunningView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant'] },
      },
      {
        path: 'tasks',
        name: 'Tasks',
        component: () => import('../views/operations/TasksView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker', 'office_worker'] },
      },
      {
        path: 'electricity',
        name: 'Electricity',
        component: () => import('../views/operations/ElectricityView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'park_worker'] },
      },
      {
        path: 'mail',
        component: () => import('../views/mail/MailView.vue'),
        meta: { roles: ['admin', 'main_manager', 'office_worker'] },
        children: [
          {
            path: '',
            redirect: { name: 'MailCompose' },
          },
          {
            path: 'compose',
            name: 'MailCompose',
            component: () => import('../views/mail/MailComposeView.vue'),
          },
          {
            path: 'sent',
            name: 'MailSent',
            component: () => import('../views/mail/MailSentView.vue'),
          },
        ],
      },
      {
        path: 'mail-templates',
        name: 'MailTemplates',
        component: () => import('../views/mail/MailTemplatesView.vue'),
        meta: { roles: ['admin', 'main_manager', 'office_worker'] },
      },
      {
        path: 'reports',
        name: 'Reports',
        component: () => import('../views/reports/ReportsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'accountant', 'office_worker', 'customer_service'] },
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('../views/settings/SettingsView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'settings/system',
        name: 'SystemSettings',
        component: () => import('../views/settings/SystemSettingsView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'settings/document-templates',
        name: 'DocumentTemplates',
        component: () => import('../views/settings/DocumentTemplatesView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'settings/document-templates/:id/edit',
        name: 'DocumentTemplateEdit',
        component: () => import('../views/settings/DocumentTemplateEditView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('../views/admin/UsersView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'admin/users',
        name: 'AdminUsers',
        component: () => import('../views/admin/AdminUsersView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'admin/employees',
        name: 'AdminEmployees',
        component: () => import('../views/admin/AdminEmployeesView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'admin/reference-data',
        name: 'ReferenceData',
        component: () => import('../views/admin/ReferenceDataView.vue'),
        meta: { roles: ['admin'] },
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('../views/user/ProfileView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant', 'office_worker', 'park_worker', 'customer_service'] },
      },
      {
        path: 'notifications',
        name: 'Notifications',
        component: () => import('../views/user/NotificationsView.vue'),
        meta: { roles: ['admin', 'main_manager', 'rental_manager', 'accountant', 'office_worker', 'park_worker', 'customer_service'] },
      },
    ],
  },
  {
    path: '/404',
    name: 'NotFound',
    component: () => import('../views/auth/NotFoundView.vue'),
    meta: { public: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/404',
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
