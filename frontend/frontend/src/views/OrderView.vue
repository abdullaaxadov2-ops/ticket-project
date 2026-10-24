<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRoute } from 'vue-router'
import { eventsApi } from '@/api/events'
import { ordersApi } from '@/api/orders'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { goToPayment, startPayment } from '@/utils/payment'
import { formatDateTime, formatMoney, formatTime, parseDate } from '@/utils/format'
import AlertMessage from '@/components/AlertMessage.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const route = useRoute()
const auth = useAuthStore()

const order = ref(null)
const event = ref(null)
const ticketTypeNames = ref({})
const loading = ref(true)
const error = ref('')
const paying = ref(false)
const payError = ref('')
const now = ref(Date.now())

// Обновляем «сейчас» раз в секунду, чтобы шёл обратный отсчёт до конца резерва
const timer = setInterval(() => {
  now.value = Date.now()
}, 1000)

onBeforeUnmount(() => clearInterval(timer))

const secondsLeft = computed(() => {
  const expiresAt = parseDate(order.value?.expires_at)

  return expiresAt ? Math.max(Math.floor((expiresAt.getTime() - now.value) / 1000), 0) : null
})

const countdown = computed(() => {
  if (secondsLeft.value === null) {
    return ''
  }

  const minutes = Math.floor(secondsLeft.value / 60)
  const seconds = String(secondsLeft.value % 60).padStart(2, '0')

  return `${minutes}:${seconds}`
})

const isOwner = computed(() => order.value?.user_id === auth.user?.id)

// Платёж уже создан: возвращаемся по сохранённой ссылке, не вызывая /pay.
// После 15 минут /pay ответит 422 «время истекло», хотя платёж в платёжке ещё может быть жив
const canResume = computed(() => isOwner.value && order.value?.payment_url !== null)

const canPay = computed(() => isOwner.value && (secondsLeft.value === null || secondsLeft.value > 0))

async function load() {
  try {
    order.value = await ordersApi.show(route.params.id)

    const [loadedEvent, ticketTypes] = await Promise.all([
      eventsApi.showCached(order.value.event_id),
      eventsApi.ticketTypes(order.value.event_id).catch(() => []),
    ])

    event.value = loadedEvent
    ticketTypeNames.value = Object.fromEntries(ticketTypes.map((ticketType) => [ticketType.id, ticketType.name]))
  } catch (e) {
    error.value = e.response?.status === 403 || e.response?.status === 404 ? 'Заказ не найден.' : errorMessage(e)
  } finally {
    loading.value = false
  }
}

load()

async function pay() {
  paying.value = true
  payError.value = ''

  try {
    await startPayment(order.value.id)
  } catch (e) {
    payError.value = errorMessage(e)
    paying.value = false
  }
}
</script>

<template>
  <p v-if="loading" class="text-slate-500">Загрузка…</p>

  <AlertMessage v-else-if="error" type="error">{{ error }}</AlertMessage>

  <div v-else-if="order" class="mx-auto max-w-3xl space-y-6">
    <RouterLink :to="{ name: 'orders' }" class="link text-sm">← Все заказы</RouterLink>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <h1 class="text-2xl font-bold">Заказ #{{ order.id }}</h1>
      <StatusBadge kind="order" :status="order.status" />
    </div>

    <div class="card space-y-1 text-sm text-slate-700">
      <p>
        <span class="mr-1 font-medium">Мероприятие:</span>
        <RouterLink v-if="event" :to="{ name: 'event', params: { id: event.id } }" class="link">
          {{ event.title }}
        </RouterLink>
        <template v-else>#{{ order.event_id }} (недоступно для просмотра)</template>
      </p>
      <p v-if="event"><span class="font-medium">Начало:</span> {{ formatDateTime(event.starts_at) }}</p>
      <p><span class="font-medium">Создан:</span> {{ formatDateTime(order.created_at) }}</p>
    </div>

    <AlertMessage v-if="order.refund_required" type="warning">
      По этому заказу требуется возврат денег (например, мероприятие отменено). Возврат оформит организатор.
    </AlertMessage>

    <div class="card overflow-x-auto p-0">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-slate-600">
          <tr>
            <th class="px-5 py-3 font-medium">Тип билета</th>
            <th class="px-5 py-3 font-medium">Цена</th>
            <th class="px-5 py-3 font-medium">Кол-во</th>
            <th class="px-5 py-3 text-right font-medium">Сумма</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in order.items" :key="item.id" class="border-b border-slate-100 last:border-0">
            <td class="px-5 py-3">{{ ticketTypeNames[item.ticket_type_id] ?? `Тип #${item.ticket_type_id}` }}</td>
            <td class="px-5 py-3">{{ formatMoney(item.price) }}</td>
            <td class="px-5 py-3">{{ item.quantity }}</td>
            <td class="px-5 py-3 text-right">{{ formatMoney(Number(item.price) * item.quantity) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="font-semibold">
            <td class="px-5 py-3" colspan="3">Итого</td>
            <td class="px-5 py-3 text-right">{{ formatMoney(order.total_amount) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div v-if="order.status === 'pending'" class="card space-y-3">
      <template v-if="canResume">
        <p class="text-sm text-slate-700">
          Оплата уже начата. Статус заказа обновится, когда платёжная система подтвердит платёж.
        </p>
        <button type="button" class="btn btn-primary" @click="goToPayment(order.id, order.payment_url)">
          Вернуться к оплате
        </button>
      </template>

      <template v-else-if="canPay">
        <p v-if="secondsLeft !== null" class="text-sm text-slate-700">
          Билеты зарезервированы до {{ formatTime(order.expires_at) }}. Осталось
          <span class="font-mono font-semibold">{{ countdown }}</span>.
        </p>

        <button type="button" class="btn btn-primary" :disabled="paying" @click="pay">
          {{ paying ? 'Переходим к оплате…' : 'Оплатить' }}
        </button>
      </template>

      <p v-else-if="isOwner" class="text-sm text-slate-700">
        Время на оплату истекло. Скоро заказ станет «Просрочен», а билеты вернутся в продажу.
      </p>

      <p v-else class="text-sm text-slate-700">Заказ ожидает оплаты покупателем.</p>

      <AlertMessage v-if="payError" type="error">{{ payError }}</AlertMessage>
    </div>

    <RouterLink v-if="order.status === 'paid' && isOwner" :to="{ name: 'tickets' }" class="btn btn-primary">
      Перейти к билетам
    </RouterLink>
  </div>
</template>
