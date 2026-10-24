<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ticketsApi } from '@/api/tickets'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/utils/format'
import AlertMessage from '@/components/AlertMessage.vue'
import AppPagination from '@/components/AppPagination.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const page = ref(null)
const loading = ref(false)
const error = ref('')

async function load() {
  if (route.name !== 'tickets') {
    return
  }

  loading.value = true
  error.value = ''

  try {
    // TicketService::getList сразу подгружает event и ticketType, отдельные запросы не нужны
    page.value = await ticketsApi.list(route.query.page || 1)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

watch(() => route.query.page, load, { immediate: true })

function changePage(newPage) {
  router.push({ query: { page: newPage } })
}
</script>

<template>
  <section>
    <h1 class="mb-6 text-2xl font-bold">{{ auth.isParticipant ? 'Мои билеты' : 'Билеты' }}</h1>

    <AlertMessage v-if="error" type="error" class="mb-6">{{ error }}</AlertMessage>

    <p v-if="loading && !page" class="text-slate-500">Загрузка…</p>

    <template v-else-if="page">
      <div v-if="page.data.length === 0" class="card text-center text-slate-600">
        Билетов пока нет. Они появятся здесь сразу после оплаты заказа.
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" :class="{ 'opacity-60': loading }">
        <RouterLink
          v-for="ticket in page.data"
          :key="ticket.id"
          :to="{ name: 'ticket', params: { id: ticket.id } }"
          class="card flex flex-col gap-2 transition hover:border-indigo-300"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="font-mono text-lg font-semibold tracking-wider">{{ ticket.number }}</span>
            <StatusBadge kind="ticket" :status="ticket.status" />
          </div>
          <p class="font-medium">{{ ticket.event?.title }}</p>
          <p class="text-sm text-slate-600">{{ ticket.ticket_type?.name }}</p>
          <p class="text-xs text-slate-500">📅 {{ formatDateTime(ticket.event?.starts_at) }}</p>
        </RouterLink>
      </div>

      <AppPagination :page="page.current_page" :last-page="page.last_page" @change="changePage" />
    </template>
  </section>
</template>
