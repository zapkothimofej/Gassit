<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()

interface NavItem {
  label: string
  path: string
  icon: string
  roles: string[]
}

const navItems: NavItem[] = [
  { label: 'Dashboard',       path: '/dashboard',      icon: '📊', roles: ['admin','main_manager','rental_manager','accountant','office_worker','park_worker','customer_service'] },
  { label: 'Parks',           path: '/parks',          icon: '🏕️', roles: ['admin','main_manager'] },
  { label: 'Units',           path: '/units',          icon: '🏠', roles: ['admin','main_manager','rental_manager','park_worker'] },
  { label: 'Applications',    path: '/applications',   icon: '📝', roles: ['admin','main_manager','rental_manager','office_worker','customer_service'] },
  { label: 'Contracts',       path: '/contracts',      icon: '📄', roles: ['admin','main_manager','rental_manager','accountant'] },
  { label: 'Customers',       path: '/customers',      icon: '👥', roles: ['admin','main_manager','rental_manager','office_worker','customer_service'] },
  { label: 'Invoices',        path: '/invoices',       icon: '🧾', roles: ['admin','main_manager','accountant'] },
  { label: 'Deposits',        path: '/deposits',       icon: '💰', roles: ['admin','main_manager','accountant','rental_manager'] },
  { label: 'Dunning',         path: '/dunning',        icon: '⚠️', roles: ['admin','main_manager','accountant'] },
  { label: 'Damage Reports',  path: '/damage-reports', icon: '🔧', roles: ['admin','main_manager','rental_manager','park_worker'] },
  { label: 'Vendors',         path: '/vendors',        icon: '🏪', roles: ['admin','main_manager','rental_manager'] },
  { label: 'Electricity',     path: '/electricity',    icon: '⚡', roles: ['admin','main_manager','rental_manager','park_worker'] },
  { label: 'Tasks',           path: '/tasks',          icon: '✅', roles: ['admin','main_manager','rental_manager','park_worker','office_worker'] },
  { label: 'Mail',            path: '/mail',           icon: '✉️', roles: ['admin','main_manager','office_worker'] },
  { label: 'Reports',         path: '/reports',        icon: '📈', roles: ['admin','main_manager','accountant','office_worker','customer_service'] },
  { label: 'Users',           path: '/users',          icon: '👤', roles: ['admin'] },
  { label: 'Settings',        path: '/settings',       icon: '⚙️', roles: ['admin'] },
]

const visibleItems = computed(() =>
  navItems.filter((item) => auth.role && item.roles.includes(auth.role)),
)
</script>

<template>
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="sidebar-logo">GASSIT</span>
    </div>
    <nav class="sidebar-nav">
      <RouterLink
        v-for="item in visibleItems"
        :key="item.path"
        :to="item.path"
        class="nav-item"
        :class="{ active: route.path.startsWith(item.path) }"
      >
        <span class="nav-icon">{{ item.icon }}</span>
        <span class="nav-label">{{ item.label }}</span>
      </RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 220px;
  background: #1e293b;
  color: #cbd5e1;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-header {
  padding: 1.25rem 1rem;
  font-size: 1.25rem;
  font-weight: 700;
  color: #f8fafc;
  border-bottom: 1px solid #334155;
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 0.5rem 0;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 1rem;
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.875rem;
  transition: background 0.15s, color 0.15s;
}

.nav-item:hover,
.nav-item.active {
  background: #334155;
  color: #f8fafc;
}

.nav-icon {
  font-size: 1rem;
  width: 1.25rem;
  text-align: center;
}
</style>
