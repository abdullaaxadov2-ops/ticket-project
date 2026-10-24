import { ref } from 'vue'
import { defineStore } from 'pinia'
import { dictionariesApi } from '@/api/dictionaries'

// Категории и места меняются редко: загружаем один раз и используем на всех страницах
export const useDictionariesStore = defineStore('dictionaries', () => {
  const categories = ref([])
  const venues = ref([])

  let loadPromise = null

  function load() {
    loadPromise ??= Promise.all([dictionariesApi.categories(), dictionariesApi.venues()])
      .then(([loadedCategories, loadedVenues]) => {
        categories.value = loadedCategories
        venues.value = loadedVenues
      })
      .catch((error) => {
        loadPromise = null
        throw error
      })

    return loadPromise
  }

  function categoryName(id) {
    return categories.value.find((category) => category.id === id)?.name ?? '—'
  }

  function venue(id) {
    return venues.value.find((item) => item.id === id) ?? null
  }

  return { categories, venues, load, categoryName, venue }
})
