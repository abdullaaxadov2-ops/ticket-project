<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi } from '@/api/events'
import { ordersApi } from '@/api/orders'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { useDictionariesStore } from '@/stores/dictionaries'
import { startPayment } from '@/utils/payment'
import { formatDateTime, formatMoney } from '@/utils/format'
import AlertMessage from '@/components/AlertMessage.vue'
import StatusBadge from '@/components/StatusBadge.vue'

// Бэкенд не даёт заказать больше 10 билетов одного типа за раз (CreateOrderRequest)
const MAX_PER_TYPE = 10

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const dictionaries = useDictionariesStore()

const event = ref(null)
const ticketTypes = ref([])
const quantities = reactive({})
const loading = ref(true)
const notFound = ref(false)
const buying = ref(false)
const buyError = ref('')
const createdOrderId = ref(null)

const venue = computed(() => (event.value ? dictionaries.venue(event.value.venue_id) : null))

function available(ticketType) {
  return Math.max(ticketType.quantity - ticketType.sold_count, 0)
}

function maxFor(ticketType) {
  return Math.min(available(ticketType), MAX_PER_TYPE)
}

// Атрибут max у input не мешает вписать руками 50, поэтому поправляем значение сами
function clamp(ticketType) {
  const value = Math.floor(Number(quantities[ticketType.id]) || 0)

  quantities[ticketType.id] = Math.min(Math.max(value, 0), maxFor(ticketType))
}

const selectedItems = computed(() =>
  ticketTypes.value
    .map((ticketType) => ({ ticketType, quantity: Number(quantities[ticketType.id] || 0) }))
    .filter((item) => item.quantity > 0),
)

const total = computed(() =>
  selectedItems.value.reduce((sum, item) => sum + Number(item.ticketType.price) * item.quantity, 0),
)

async function loadTicketTypes() {
  ticketTypes.value = await eventsApi.ticketTypes(route.params.id)

  for (const ticketType of ticketTypes.value) {
    clamp(ticketType)
  }
}

async function load() {
  dictionaries.load().catch(() => {})

  try {
    event.value = await eventsApi.show(route.params.id)
    await loadTicketTypes()
  } catch (e) {
    // Черновик, отменённое и завершённое мероприятие бэкенд отдаёт посторонним как 404
    if (e.response?.status === 404) {
      notFound.value = true
    } else {
      buyError.value = errorMessage(e)
    }
  } finally {
    loading.value = false
  }
}

load()

async function buy() {
  if (auth.isGuest) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })

    return
  }

  buying.value = true
  buyError.value = ''

  let order

  try {
    order = await ordersApi.create(
      event.value.id,
      selectedItems.value.map((item) => ({ ticket_type_id: item.ticketType.id, quantity: item.quantity })),
    )
  } catch (e) {
    buyError.value = errorMessage(e)
    buying.value = false
    // Остаток мог измениться, пока пользователь выбирал билеты
    loadTicketTypes().catch(() => {})

    return
  }

  try {
    await startPayment(order.id)
  } catch (e) {
    // Заказ уже создан и билеты зарезервированы: оплатить можно со страницы заказа
    createdOrderId.value = order.id
    buyError.value = errorMessage(e)
    buying.value = false
  }
}
</script>

<template>
  <p v-if="loading" class="text-slate-500">Загрузка…</p>

  <div v-else-if="notFound" class="card text-center">
    <h1 class="mb-2 text-xl font-semibold">Мероприятие недоступно</h1>
    <p class="mb-4 text-slate-600">Его не существует, оно ещё не опубликовано, отменено или уже прошло.</p>
    <RouterLink :to="{ name: 'events' }" class="link">← Ко всем мероприятиям</RouterLink>
  </div>

  <div v-else-if="event" class="grid gap-8 lg:grid-cols-3">
    <section class="lg:col-span-2">
      <RouterLink :to="{ name: 'events' }" class="link text-sm">← Все мероприятия</RouterLink>

      <div class="mt-4 mb-2 flex flex-wrap items-center gap-3">
        <span class="text-xs font-medium tracking-wide text-indigo-600 uppercase">
          {{ dictionaries.categoryName(event.category_id) }}
        </span>
        <StatusBadge v-if="event.status !== 'published'" kind="event" :status="event.status" />
      </div>

      <h1 class="mb-4 text-3xl font-bold">{{ event.title }}</h1>

      <div class="card mb-6 space-y-2 text-sm text-slate-700">
        <p><span class="font-medium">Начало:</span> {{ formatDateTime(event.starts_at) }}</p>
        <p><span class="font-medium">Окончание:</span> {{ formatDateTime(event.ends_at) }}</p>
        <p v-if="venue">
          <span class="font-medium">Место:</span> {{ venue.name }}, {{ venue.address }}
        </p>
      </div>

      <p v-if="event.description" class="leading-relaxed whitespace-pre-line text-slate-700">{{ event.description }}</p>
    </section>

    <aside class="card h-fit">
      <h2 class="mb-4 text-lg font-semibold">Билеты</h2>

      <p v-if="ticketTypes.length === 0" class="text-sm text-slate-500">Билеты пока не продаются.</p>

      <ul v-else class="mb-4 space-y-4">
        <li v-for="ticketType in ticketTypes" :key="ticketType.id" class="border-b border-slate-100 pb-4 last:border-0">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-medium">{{ ticketType.name }}</p>
              <p v-if="ticketType.description" class="text-xs text-slate-500">{{ ticketType.description }}</p>
              <p class="mt-1 text-sm">{{ formatMoney(ticketType.price) }}</p>
              <p class="text-xs" :class="available(ticketType) > 0 ? 'text-slate-500' : 'text-red-600'">
                {{ available(ticketType) > 0 ? `Осталось: ${available(ticketType)}` : 'Распроданы' }}
              </p>
            </div>

            <input
              v-model.number="quantities[ticketType.id]"
              class="input w-20"
              type="number"
              min="0"
              :max="maxFor(ticketType)"
              :disabled="event.status !== 'published' || available(ticketType) === 0"
              :aria-label="`Количество: ${ticketType.name}`"
              @change="clamp(ticketType)"
            />
          </div>
        </li>
      </ul>

      <template v-if="event.status === 'published' && ticketTypes.length > 0">
        <p class="mb-4 flex justify-between font-semibold">
          <span>Итого</span>
          <span>{{ formatMoney(total) }}</span>
        </p>

        <AlertMessage v-if="!auth.isGuest && !auth.isParticipant" type="info" class="mb-4">
          Покупать билеты могут только участники.
        </AlertMessage>

        <button
          type="button"
          class="btn btn-primary w-full"
          :disabled="buying || selectedItems.length === 0 || (!auth.isGuest && !auth.isParticipant)"
          @click="buy"
        >
          {{ buying ? 'Оформляем…' : auth.isGuest ? 'Войти и купить' : 'Купить и оплатить' }}
        </button>

        <!-- Заглушка: рассрочки пока нет ни во фронте, ни в бэкенде -->
        <button
            type="button"
            class="btn mt-3 w-full cursor-not-allowed bg-green-600 text-white disabled:opacity-80"
            disabled
        >
          Рассрочка от Alif
          <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs font-medium text-white">скоро</span>
        </button>

        <p class="mt-2 text-xs text-slate-500">Билеты резервируются на 15 минут до оплаты.</p>
      </template>

      <AlertMessage v-if="buyError" type="error" class="mt-4">
        {{ buyError }}
        <template v-if="createdOrderId">
          <br />
          Заказ создан, оплатить его можно
          <RouterLink :to="{ name: 'order', params: { id: createdOrderId } }" class="font-medium underline">
            на странице заказа
          </RouterLink>.
        </template>
      </AlertMessage>
    </aside>
  </div>
</template>
