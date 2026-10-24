<script setup>
import { reactive, ref } from 'vue'
import { profileApi } from '@/api/profile'
import { errorMessage, validationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime, ROLE_LABELS } from '@/utils/format'
import AlertMessage from '@/components/AlertMessage.vue'
import FieldError from '@/components/FieldError.vue'

const auth = useAuthStore()

const name = ref(auth.user?.name ?? '')
const nameErrors = ref({})
const nameMessage = ref(null)
const savingName = ref(false)

// Названия полей как в ChangePasswordRequest: правило confirmed ждёт new_password_confirmation
const password = reactive({ current_password: '', new_password: '', new_password_confirmation: '' })
const passwordErrors = ref({})
const passwordMessage = ref(null)
const savingPassword = ref(false)

async function saveName() {
  savingName.value = true
  nameErrors.value = {}
  nameMessage.value = null

  try {
    auth.user = await profileApi.changeName(name.value)
    nameMessage.value = { type: 'success', text: 'Имя сохранено.' }
  } catch (e) {
    nameErrors.value = validationErrors(e)
    nameMessage.value = { type: 'error', text: errorMessage(e) }
  } finally {
    savingName.value = false
  }
}

async function savePassword() {
  savingPassword.value = true
  passwordErrors.value = {}
  passwordMessage.value = null

  try {
    await profileApi.changePassword({ ...password })
    Object.assign(password, { current_password: '', new_password: '', new_password_confirmation: '' })
    passwordMessage.value = { type: 'success', text: 'Пароль изменён.' }
  } catch (e) {
    passwordErrors.value = validationErrors(e)
    passwordMessage.value = { type: 'error', text: errorMessage(e) }
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <!-- После выхода user становится null раньше, чем роутер уведёт со страницы -->
  <div v-if="auth.user" class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold">Профиль</h1>

    <div class="card space-y-1 text-sm text-slate-700">
      <p><span class="font-medium">Email:</span> {{ auth.user.email }}</p>
      <p><span class="font-medium">Роль:</span> {{ ROLE_LABELS[auth.user.role] }}</p>
      <p>
        <span class="mr-1 font-medium">Почта:</span>
        <template v-if="auth.user.email_verified_at">подтверждена {{ formatDateTime(auth.user.email_verified_at) }}</template>
        <template v-else>
          не подтверждена ·
          <RouterLink :to="{ name: 'verify', query: { email: auth.user.email } }" class="link">ввести код</RouterLink>
        </template>
      </p>
    </div>

    <form class="card space-y-4" @submit.prevent="saveName">
      <h2 class="font-semibold">Имя</h2>

      <AlertMessage v-if="nameMessage" :type="nameMessage.type">{{ nameMessage.text }}</AlertMessage>

      <div>
        <label class="label" for="name">Как к вам обращаться</label>
        <input id="name" v-model.trim="name" class="input" required maxlength="255" />
        <FieldError :messages="nameErrors.name" />
      </div>

      <button type="submit" class="btn btn-primary" :disabled="savingName">Сохранить</button>
    </form>

    <form class="card space-y-4" @submit.prevent="savePassword">
      <h2 class="font-semibold">Смена пароля</h2>

      <AlertMessage v-if="passwordMessage" :type="passwordMessage.type">{{ passwordMessage.text }}</AlertMessage>

      <div>
        <label class="label" for="current_password">Текущий пароль</label>
        <input
          id="current_password"
          v-model="password.current_password"
          class="input"
          type="password"
          autocomplete="current-password"
          required
        />
        <FieldError :messages="passwordErrors.current_password" />
      </div>

      <div>
        <label class="label" for="new_password">Новый пароль (не короче 8 символов)</label>
        <input
          id="new_password"
          v-model="password.new_password"
          class="input"
          type="password"
          autocomplete="new-password"
          required
        />
        <FieldError :messages="passwordErrors.new_password" />
      </div>

      <div>
        <label class="label" for="new_password_confirmation">Повторите новый пароль</label>
        <input
          id="new_password_confirmation"
          v-model="password.new_password_confirmation"
          class="input"
          type="password"
          autocomplete="new-password"
          required
        />
      </div>

      <button type="submit" class="btn btn-primary" :disabled="savingPassword">Изменить пароль</button>
    </form>
  </div>
</template>
