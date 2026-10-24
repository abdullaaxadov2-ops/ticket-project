<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import AlertMessage from '@/components/AlertMessage.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const error = ref('')
const submitting = ref(false)

async function submit() {
  submitting.value = true
  error.value = ''

  try {
    await auth.login(form.email, form.password)
    router.push(route.query.redirect || { name: 'events' })
  } catch (e) {
    // AuthController::login на любую ошибку (неверный пароль, блокировка) отвечает «invalid credentials»
    error.value = e.response?.status === 422 ? 'Неверный email или пароль.' : errorMessage(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-md">
    <h1 class="mb-6 text-2xl font-bold">Вход</h1>

    <form class="card space-y-4" @submit.prevent="submit">
      <AlertMessage v-if="error" type="error">{{ error }}</AlertMessage>

      <div>
        <label class="label" for="email">Email</label>
        <input id="email" v-model.trim="form.email" class="input" type="email" autocomplete="email" required />
      </div>

      <div>
        <label class="label" for="password">Пароль</label>
        <input
          id="password"
          v-model="form.password"
          class="input"
          type="password"
          autocomplete="current-password"
          required
        />
      </div>

      <button type="submit" class="btn btn-primary w-full" :disabled="submitting">
        {{ submitting ? 'Входим…' : 'Войти' }}
      </button>

      <p class="text-center text-sm text-slate-600">
        Нет аккаунта?
        <RouterLink :to="{ name: 'register' }" class="link">Зарегистрироваться</RouterLink>
      </p>
    </form>
  </div>
</template>
