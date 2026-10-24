<script setup>
import { reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi } from '@/api/events'
import { ordersApi } from '@/api/orders'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime, formatMoney } from '@/utils/format'
import AlertMessage from '@/components/AlertMessage.vue'
import AppPagination from '@/components/AppPagination.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const page = ref(null)
const eventTitles = reactive({})
const loading = ref(false)
const error = ref('')

// В заказе есть только event_id: названия догружаем отдельно, каждое мероприятие один раз
function loadEventTitles(orders) {
  for (const id of new Set(orders.map((order) => order.event_id))) {
    eventsApi.showCached(id).then((event) => {
      eventTitles[id] = event?.title ?? `Мероприятие #${id}`
    })
  }
}

async function load() {
  if (route.name !== 'orders') {
    return
  }

  loading.value = true
  error.value = ''

  try {
    page.value = await ordersApi.list(route.query.page || 1)
    loadEventTitles(page.value.data)
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

function ticketCount(order) {
  return order.items.reduce((sum, item) => sum + item.quantity, 0)
}
</script>

<template>
  <section>
    <h1 class="mb-6 text-2xl font-bold">{{ auth.isParticipant ? 'Мои заказы' : 'Заказы' }}</h1>

    <AlertMessage v-if="error" type="error" class="mb-6">{{ error }}</AlertMessage>

    <p v-if="loading && !page" class="text-slate-500">Загрузка…</p>

    <template v-else-if="page">
      <div v-if="page.data.length === 0" class="card text-center text-slate-600">
        Заказов пока нет.
        <RouterLink :to="{ name: 'events' }" class="link">Выбрать мероприятие</RouterLink>
      </div>

      <ul v-else class="space-y-3" :class="{ 'opacity-60': loading }">
        <li v-for="order in page.data" :key="order.id">
          <RouterLink
            :to="{ name: 'order', params: { id: order.id } }"
            class="card flex flex-wrap items-center justify-between gap-4 transition hover:border-indigo-300"
          >
            <div>
              <p class="font-semibold">Заказ #{{ order.id }}</p>
              <p class="text-sm text-slate-600">{{ eventTitles[order.event_id] ?? '…' }}</p>
              <p class="text-xs text-slate-500">{{ formatDateTime(order.created_at) }} · билетов: {{ ticketCount(order) }}</p>
            </div>

            <div class="flex flex-col items-end gap-1">
              <span class="font-semibold">{{ formatMoney(order.total_amount) }}</span>
              <StatusBadge kind="order" :status="order.status" />
              <span v-if="order.refund_required" class="text-xs font-medium text-red-600">Требуется возврат</span>
            </div>
          </RouterLink>
        </li>
      </ul>

      <AppPagination :page="page.current_page" :last-page="page.last_page" @change="changePage" />
    </template>
  </section>
</template>
