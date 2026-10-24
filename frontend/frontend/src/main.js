import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setUnauthorizedHandler } from './api/http'
import { useAuthStore } from './stores/auth'
import './assets/main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

// Сессия истекла или пользователь вышел в другой вкладке: забываем его и отправляем на вход
setUnauthorizedHandler(() => {
  const auth = useAuthStore()
  auth.user = null

  const current = router.currentRoute.value
  if (current.meta.auth) {
    router.push({ name: 'login', query: { redirect: current.fullPath } })
  }
})

app.mount('#app')
