<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { authApi } from '@/api/auth'
import { errorMessage, validationErrors } from '@/api/http'
import AlertMessage from '@/components/AlertMessage.vue'
import FieldError from '@/components/FieldError.vue'

const router = useRouter()

const form = reactive({ email: '', password: '', passwordConfirmation: '' })
const errors = ref({})
const error = ref('')
const submitting = ref(false)

async function submit() {
  errors.value = {}
  error.value = ''

  // Совпадение паролей проверяем только здесь: бэкенд поле подтверждения не принимает
  if (form.password !== form.passwordConfirmation) {
    errors.value = { passwordConfirmation: ['Пароли не совпадают.'] }

    return
  }

  submitting.value = true

  try {
    await authApi.register(form.email, form.password)
    router.push({ name: 'verify', query: { email: form.email } })
  } catch (e) {
    errors.value = validationErrors(e)

    if (Object.keys(errors.value).length === 0) {
      error.value = errorMessage(e)
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-md">
    <h1 class="mb-6 text-2xl font-bold">Регистрация</h1>

    <form class="card space-y-4" @submit.prevent="submit">
      <AlertMessage v-if="error" type="error">{{ error }}</AlertMessage>

      <div>
        <label class="label" for="email">Email</label>
        <input id="email" v-model.trim="form.email" class="input" type="email" autocomplete="email" required />
        <FieldError :messages="errors.email" />
      </div>

      <div>
        <label class="label" for="password">Пароль</label>
        <input
          id="password"
          v-model="form.password"
          class="input"
          type="password"
          autocomplete="new-password"
          required
        />
        <FieldError :messages="errors.password" />
      </div>

      <div>
        <label class="label" for="password_confirmation">Повторите пароль</label>
        <input
          id="password_confirmation"
          v-model="form.passwordConfirmation"
          class="input"
          type="password"
          autocomplete="new-password"
          required
        />
        <FieldError :messages="errors.passwordConfirmation" />
      </div>

      <button type="submit" class="btn btn-primary w-full" :disabled="submitting">
        {{ submitting ? 'Регистрируем…' : 'Зарегистрироваться' }}
      </button>

      <p class="text-center text-sm text-slate-600">
        Уже есть аккаунт?
        <RouterLink :to="{ name: 'login' }" class="link">Войти</RouterLink>
      </p>
    </form>
  </div>
</template>
