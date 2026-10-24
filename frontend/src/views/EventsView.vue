<script setup>
import { reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi } from '@/api/events'
import { errorMessage } from '@/api/http'
import { useDictionariesStore } from '@/stores/dictionaries'
import AlertMessage from '@/components/AlertMessage.vue'
import AppPagination from '@/components/AppPagination.vue'
import EventCard from '@/components/EventCard.vue'

const route = useRoute()
const router = useRouter()
const dictionaries = useDictionariesStore()

// Названия полей совпадают с EventListRequest на бэкенде
const FILTER_KEYS = ['search', 'category_id', 'venue_id', 'date_from', 'date_to', 'sort']

const filters = reactive(Object.fromEntries(FILTER_KEYS.map((key) => [key, ''])))
const page = ref(null)
const loading = ref(false)
const error = ref('')

dictionaries.load().catch(() => {})

// Фильтры живут в адресе страницы (?search=...&page=2): ссылкой можно поделиться, «Назад» работает
async function load() {
  // При уходе на страницу мероприятия query тоже меняется, лишний запрос не нужен
  if (route.name !== 'events') {
    return
  }

  for (const key of FILTER_KEYS) {
    filters[key] = route.query[key] ?? ''
  }

  loading.value = true
  error.value = ''

  try {
    page.value = await eventsApi.list({ ...route.query, per_page: 12 })
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

watch(() => route.query, load, { immediate: true })

function applyFilters() {
  const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''))

  router.push({ query })
}

function resetFilters() {
  router.push({ query: {} })
}

function changePage(newPage) {
  router.push({ query: { ...route.query, page: newPage } })
}
</script>

<template>
  <section>
    <h1 class="mb-6 text-2xl font-bold">Мероприятия</h1>

    <form class="card mb-8 grid gap-4 md:grid-cols-3 lg:grid-cols-6" @submit.prevent="applyFilters">
      <div class="md:col-span-3 lg:col-span-2">
        <label class="label" for="search">Поиск</label>
        <input id="search" v-model="filters.search" class="input" type="search" placeholder="Название мероприятия" />
      </div>

      <div>
        <label class="label" for="category">Категория</label>
        <select id="category" v-model="filters.category_id" class="input">
          <option value="">Все</option>
          <option v-for="category in dictionaries.categories" :key="category.id" :value="String(category.id)">
            {{ category.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="label" for="venue">Место</label>
        <select id="venue" v-model="filters.venue_id" class="input">
          <option value="">Все</option>
          <option v-for="venue in dictionaries.venues" :key="venue.id" :value="String(venue.id)">
            {{ venue.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="label" for="date_from">С даты</label>
        <input id="date_from" v-model="filters.date_from" class="input" type="date" />
      </div>

      <div>
        <label class="label" for="date_to">По дату</label>
        <input id="date_to" v-model="filters.date_to" class="input" type="date" />
      </div>

      <div class="md:col-span-1 lg:col-span-2">
        <label class="label" for="sort">Сортировка</label>
        <select id="sort" v-model="filters.sort" class="input">
          <option value="">Сначала ближайшие</option>
          <option value="-date">Сначала поздние</option>
          <option value="price">Сначала дешёвые</option>
          <option value="-price">Сначала дорогие</option>
        </select>
      </div>

      <div class="flex items-end gap-2 md:col-span-2 lg:col-span-4 lg:justify-end">
        <button type="button" class="btn btn-secondary" @click="resetFilters">Сбросить</button>
        <button type="submit" class="btn btn-primary">Найти</button>
      </div>
    </form>

    <AlertMessage v-if="error" type="error" class="mb-6">{{ error }}</AlertMessage>

    <p v-if="loading && !page" class="text-slate-500">Загрузка…</p>

    <template v-else-if="page">
      <p v-if="page.data.length === 0" class="text-slate-500">По вашему запросу ничего не нашлось.</p>

      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" :class="{ 'opacity-60': loading }">
        <EventCard
          v-for="event in page.data"
          :key="event.id"
          :event="event"
          :category-name="dictionaries.categoryName(event.category_id)"
          :venue-name="dictionaries.venue(event.venue_id)?.name ?? '—'"
        />
      </div>

      <AppPagination :page="page.current_page" :last-page="page.last_page" @change="changePage" />
    </template>
  </section>
</template>
