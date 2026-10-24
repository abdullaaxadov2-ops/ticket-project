<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ROLE_LABELS } from '@/utils/format'

const auth = useAuthStore()
const router = useRouter()

async function logout() {
  await auth.logout()
  router.push({ name: 'events' })
}
</script>

<template>
  <header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4">
      <RouterLink :to="{ name: 'events' }" class="text-lg font-semibold text-indigo-700">
        🎟 Билеты
      </RouterLink>

      <nav class="flex flex-wrap items-center gap-4 text-sm">
        <RouterLink :to="{ name: 'events' }" class="text-slate-700 hover:text-indigo-700">Мероприятия</RouterLink>

        <template v-if="auth.isGuest">
          <RouterLink :to="{ name: 'login' }" class="text-slate-700 hover:text-indigo-700">Войти</RouterLink>
          <RouterLink :to="{ name: 'register' }" class="btn btn-primary">Регистрация</RouterLink>
        </template>

        <template v-else>
          <RouterLink :to="{ name: 'orders' }" class="text-slate-700 hover:text-indigo-700">
            {{ auth.isParticipant ? 'Мои заказы' : 'Заказы' }}
          </RouterLink>
          <RouterLink :to="{ name: 'tickets' }" class="text-slate-700 hover:text-indigo-700">
            {{ auth.isParticipant ? 'Мои билеты' : 'Билеты' }}
          </RouterLink>
          <RouterLink :to="{ name: 'profile' }" class="text-slate-700 hover:text-indigo-700">
            {{ auth.user.name || auth.user.email }}
            <span class="text-xs text-slate-400">({{ ROLE_LABELS[auth.user.role] }})</span>
          </RouterLink>
          <button type="button" class="btn btn-secondary" @click="logout">Выйти</button>
        </template>
      </nav>
    </div>
  </header>
</template>
