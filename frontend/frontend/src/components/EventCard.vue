<script setup>
import StatusBadge from '@/components/StatusBadge.vue'
import { formatDateTime, formatMoney } from '@/utils/format'

defineProps({
  event: { type: Object, required: true },
  categoryName: { type: String, default: '—' },
  venueName: { type: String, default: '—' },
})
</script>

<template>
  <RouterLink
    :to="{ name: 'event', params: { id: event.id } }"
    class="card flex flex-col gap-3 transition hover:border-indigo-300 hover:shadow-md"
  >
    <div class="flex items-start justify-between gap-2">
      <span class="text-xs font-medium tracking-wide text-indigo-600 uppercase">{{ categoryName }}</span>
      <!-- Организатор видит в каталоге и свои черновики -->
      <StatusBadge v-if="event.status !== 'published'" kind="event" :status="event.status" />
    </div>

    <h3 class="text-lg font-semibold text-slate-900">{{ event.title }}</h3>

    <div class="space-y-1 text-sm text-slate-600">
      <p>📅 {{ formatDateTime(event.starts_at) }}</p>
      <p>📍 {{ venueName }}</p>
    </div>

    <p class="mt-auto pt-2 font-medium text-slate-900">
      <template v-if="event.min_price !== null">от {{ formatMoney(event.min_price) }}</template>
      <span v-else class="text-slate-400">Билеты ещё не добавлены</span>
    </p>
  </RouterLink>
</template>
