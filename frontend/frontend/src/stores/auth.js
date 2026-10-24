import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '@/api/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)

  const isGuest = computed(() => user.value === null)
  const isParticipant = computed(() => user.value?.role === 'participant')

  async function fetchUser() {
    try {
      user.value = await authApi.me()
    } catch {
      user.value = null
    }
  }

  // Роутер вызывает init перед каждым переходом, но /me запрашиваем только один раз
  let initPromise = null

  function init() {
    initPromise ??= fetchUser()

    return initPromise
  }

  async function login(email, password) {
    await authApi.login(email, password)
    await fetchUser()
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      user.value = null
    }
  }

  function hasRole(...roles) {
    return user.value !== null && roles.includes(user.value.role)
  }

  return { user, isGuest, isParticipant, init, fetchUser, login, logout, hasRole }
})
