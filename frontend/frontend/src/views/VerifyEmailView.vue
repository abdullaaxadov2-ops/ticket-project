<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { authApi } from '@/api/auth'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import AlertMessage from '@/components/AlertMessage.vue'

const route = useRoute()
const auth = useAuthStore()

const code = ref('')
const error = ref('')
const verified = ref(false)
const submitting = ref(false)
const isDev = import.meta.env.DEV

async function submit() {
  submitting.value = true
  error.value = ''

  try {
    await authApi.verify(code.value)
    verified.value = true

    if (!auth.isGuest) {
      await auth.fetchUser()
    }
  } catch (e) {
    error.value = e.response?.status === 422 ? 'Неверный или устаревший код.' : errorMessage(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-md">
    <h1 class="mb-6 text-2xl font-bold">Подтверждение почты</h1>

    <div v-if="verified" class="card space-y-4 text-center">
      <AlertMessage type="success">Почта подтверждена.</AlertMessage>
      <RouterLink v-if="auth.isGuest" :to="{ name: 'login' }" class="btn btn-primary">Войти</RouterLink>
      <RouterLink v-else :to="{ name: 'events' }" class="btn btn-primary">К мероприятиям</RouterLink>
    </div>

    <form v-else class="card space-y-4" @submit.prevent="submit">
      <p class="text-sm text-slate-600">
        Мы отправили код
        <template v-if="route.query.email">на <b>{{ route.query.email }}</b></template>.
        Он действует один час.
      </p>

      <p v-if="isDev" class="text-xs text-slate-500">
        Локально письма приходят в Mailhog:
        <a href="http://localhost:8025" target="_blank" rel="noopener" class="link">localhost:8025</a>
      </p>

      <AlertMessage v-if="error" type="error">{{ error }}</AlertMessage>

      <div>
        <label class="label" for="code">Код из письма</label>
        <input id="code" v-model.trim="code" class="input font-mono tracking-widest" autocomplete="one-time-code" required />
      </div>

      <button type="submit" class="btn btn-primary w-full" :disabled="submitting">
        {{ submitting ? 'Проверяем…' : 'Подтвердить' }}
      </button>

      <p class="text-center text-sm text-slate-600">
        Можно подтвердить позже и
        <RouterLink :to="{ name: 'login' }" class="link">войти сейчас</RouterLink>.
      </p>
    </form>
  </div>
</template>
