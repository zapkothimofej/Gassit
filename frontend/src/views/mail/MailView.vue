<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const activeTab = computed(() => {
  if (route.name === 'MailSent') return 'sent'
  return 'compose'
})
</script>

<template>
  <div class="mail-layout">
    <div class="page-header">
      <h1 class="page-title">Mail</h1>
      <div class="tabs">
        <RouterLink :to="{ name: 'MailCompose' }" class="tab" :class="{ active: activeTab === 'compose' }">
          Compose
        </RouterLink>
        <RouterLink :to="{ name: 'MailSent' }" class="tab" :class="{ active: activeTab === 'sent' }">
          Sent Log
        </RouterLink>
      </div>
    </div>
    <RouterView />
  </div>
</template>

<style scoped>
.mail-layout { display: flex; flex-direction: column; min-height: 100%; }
.page-header { display: flex; align-items: center; gap: 2rem; padding: 1.5rem 1.5rem 0; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.tabs { display: flex; gap: 0; border-bottom: 2px solid #e2e8f0; }
.tab { padding: 0.5rem 1.25rem; font-size: 0.875rem; font-weight: 500; color: #64748b; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color .15s, border-color .15s; }
.tab:hover { color: #1e293b; }
.tab.active { color: #3b82f6; border-bottom-color: #3b82f6; }
</style>
