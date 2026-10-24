<script setup>
import AppHeader from '@/components/AppHeader.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <AppHeader />

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
      <RouterView />
    </main>

    <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-500">
      Ticket Project · учебный проект на Laravel
    </footer>
  </div>
</template>
