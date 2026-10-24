<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { ticketsApi } from '@/api/tickets'
import { errorMessage } from '@/api/http'
import { useDictionariesStore } from '@/stores/dictionaries'
import { formatDateTime } from '@/utils/format'
import AlertMessage from '@/components/AlertMessage.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const route = useRoute()
const dictionaries = useDictionariesStore()

const ticket = ref(null)
const loading = ref(true)
const error = ref('')

async function load() {
  dictionaries.load().catch(() => {})

  try {
    ticket.value = await ticketsApi.show(route.params.id)
  } catch (e) {
    error.value = e.response?.status === 403 || e.response?.status === 404 ? 'Билет не найден.' : errorMessage(e)
  } finally {
    loading.value = false
  }
}

load()
</script>

<template>
  <p v-if="loading" class="text-slate-500">Загрузка…</p>

  <AlertMessage v-else-if="error" type="error">{{ error }}</AlertMessage>

  <div v-else-if="ticket" class="mx-auto max-w-md space-y-4">
    <RouterLink :to="{ name: 'tickets' }" class="link text-sm">← Все билеты</RouterLink>

    <div class="card space-y-4 text-center">
      <StatusBadge kind="ticket" :status="ticket.status" />

      <p class="font-mono text-4xl font-bold tracking-widest text-indigo-700">{{ ticket.number }}</p>

      <p v-if="ticket.status === 'active'" class="text-sm text-slate-600">Покажите этот код на входе.</p>
      <p v-else-if="ticket.status === 'used'" class="text-sm text-slate-600">Билет уже использован на входе.</p>
      <p v-else class="text-sm text-red-600">Билет аннулирован: мероприятие отменено.</p>

      <hr class="border-slate-200" />

      <div class="space-y-1 text-left text-sm text-slate-700">
        <p><span class="font-medium">Мероприятие:</span> {{ ticket.event?.title }}</p>
        <p><span class="font-medium">Начало:</span> {{ formatDateTime(ticket.event?.starts_at) }}</p>
        <p v-if="ticket.event && dictionaries.venue(ticket.event.venue_id)">
          <span class="font-medium">Место:</span>
          {{ dictionaries.venue(ticket.event.venue_id).name }}, {{ dictionaries.venue(ticket.event.venue_id).address }}
        </p>
        <p><span class="font-medium">Тип билета:</span> {{ ticket.ticket_type?.name }}</p>
        <p>
          <span class="mr-1 font-medium">Заказ:</span>
          <RouterLink :to="{ name: 'order', params: { id: ticket.order_id } }" class="link">#{{ ticket.order_id }}</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
