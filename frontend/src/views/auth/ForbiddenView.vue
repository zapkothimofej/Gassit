<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="error-page">
    <div class="error-icon">🔒</div>
    <h1 class="error-code">403</h1>
    <h2 class="error-title">Kein Zugriff</h2>
    <p class="error-msg">Du hast keine Berechtigung, diese Seite aufzurufen.</p>
    <button class="back-btn" @click="router.push('/dashboard')">Zurück zum Dashboard</button>
  </div>
</template>

<style scoped>
.error-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  gap: 0.75rem;
  background: #f8fafc;
  text-align: center;
  padding: 2rem;
}
.error-icon { font-size: 3rem; }
.error-code { font-size: 5rem; font-weight: 800; color: #e2e8f0; margin: 0; line-height: 1; }
.error-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.error-msg { font-size: 1rem; color: #64748b; margin: 0; }
.back-btn {
  margin-top: 0.5rem;
  padding: 0.625rem 1.5rem;
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
}
.back-btn:hover { background: #2563eb; }
</style>
