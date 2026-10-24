<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ordersApi } from '@/api/orders'
import { errorMessage } from '@/api/http'
import { forgetPendingOrder, pendingOrderId } from '@/utils/payment'
import AlertMessage from '@/components/AlertMessage.vue'

// Сюда платёжка возвращает пользователя после оплаты (success_url и fail_url кассы).
// Сам редирект ничего не доказывает (ТЗ §10): об оплате бэкенд узнаёт только из вебхука,
// поэтому опрашиваем статус заказа, пока он не перестанет быть pending
const POLL_INTERVAL_MS = 2000
const MAX_ATTEMPTS = 30

const route = useRoute()

const orderId = route.query.order || pendingOrderId()
const order = ref(null)
// checking | paid | failed | timeout | error
const state = ref(orderId ? 'checking' : 'error')
const error = ref(orderId ? '' : 'Не удалось определить заказ. Откройте его из списка заказов.')

let attempts = 0
let timer = null

async function check() {
  attempts++

  try {
    order.value = await ordersApi.show(orderId)
  } catch (e) {
    state.value = 'error'
    error.value = errorMessage(e)

    return
  }

  if (order.value.status === 'paid') {
    state.value = 'paid'
    forgetPendingOrder()
  } else if (order.value.status !== 'pending') {
    state.value = 'failed'
    forgetPendingOrder()
  } else if (attempts >= MAX_ATTEMPTS) {
    state.value = 'timeout'
  } else {
    timer = setTimeout(check, POLL_INTERVAL_MS)
  }
}

if (orderId) {
  check()
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="mx-auto max-w-lg">
    <h1 class="mb-6 text-2xl font-bold">Результат оплаты</h1>

    <div class="card space-y-4 text-center">
      <template v-if="state === 'checking'">
        <p class="text-slate-700">Проверяем оплату заказа #{{ orderId }}…</p>
        <p class="text-xs text-slate-500">Ждём подтверждения от платёжной системы, это занимает несколько секунд.</p>
      </template>

      <template v-else-if="state === 'paid'">
        <AlertMessage type="success">Заказ #{{ orderId }} оплачен. Билеты уже выпущены.</AlertMessage>
        <RouterLink :to="{ name: 'tickets' }" class="btn btn-primary">Мои билеты</RouterLink>
      </template>

      <template v-else-if="state === 'failed'">
        <AlertMessage type="error">Заказ #{{ orderId }} не оплачен: платёж отменён или время истекло.</AlertMessage>
        <RouterLink :to="{ name: 'events' }" class="btn btn-primary">К мероприятиям</RouterLink>
      </template>

      <template v-else-if="state === 'timeout'">
        <AlertMessage type="warning">
          Платёжная система ещё не подтвердила оплату. Статус обновится автоматически, проверьте заказ чуть позже.
        </AlertMessage>
        <RouterLink :to="{ name: 'order', params: { id: orderId } }" class="btn btn-primary">Открыть заказ</RouterLink>
      </template>

      <template v-else>
        <AlertMessage type="error">{{ error }}</AlertMessage>
        <RouterLink :to="{ name: 'orders' }" class="btn btn-primary">Мои заказы</RouterLink>
      </template>
    </div>
  </div>
</template>
