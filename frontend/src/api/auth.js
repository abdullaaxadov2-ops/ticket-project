import http, { ensureCsrfCookie } from './http'

export const authApi = {
  async login(email, password) {
    await ensureCsrfCookie()
    await http.post('/auth/login', { email, password })
  },

  // Бэкенд принимает только email и password: лишнее поле (например, password_confirmation)
  // сейчас ломает AuthRegistration::toDTO() с ошибкой 500 (недочёт №11)
  async register(email, password) {
    await http.post('/auth/register', { email, password })
  },

  async verify(code) {
    await http.post('/auth/verify', { code })
  },

  async logout() {
    await http.post('/auth/logout')
  },

  async me() {
    const { data } = await http.get('/me')

    return data.data
  },
}
