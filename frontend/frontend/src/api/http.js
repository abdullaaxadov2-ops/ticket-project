import axios from 'axios'

// Запросы идут на тот же адрес, что и сам фронт: Vite проксирует /api и /sanctum на бэкенд
const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  // Axios сам читает cookie XSRF-TOKEN и кладёт его в заголовок X-XSRF-TOKEN
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

let onUnauthorized = null

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

// Sanctum ставит cookie XSRF-TOKEN, без неё любой POST из SPA получит 419
export function ensureCsrfCookie() {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

function hasCsrfCookie() {
  return document.cookie.split('; ').some((cookie) => cookie.startsWith('XSRF-TOKEN='))
}

http.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase()

  if (method !== 'get' && !hasCsrfCookie()) {
    await ensureCsrfCookie()
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response, config } = error

    // 419: устарел CSRF-токен. Берём новый и повторяем запрос один раз
    if (response?.status === 419 && config && !config._retried) {
      config._retried = true
      await ensureCsrfCookie()

      return http(config)
    }

    if (response?.status === 401 && onUnauthorized) {
      onUnauthorized()
    }

    return Promise.reject(error)
  },
)

// Ошибки валидации Laravel: { errors: { поле: ['сообщение'] } }
export function validationErrors(error) {
  if (error.response?.status !== 422) {
    return {}
  }

  return error.response.data?.errors ?? {}
}

export function errorMessage(error) {
  const { response } = error

  if (!response) {
    return 'Сервер недоступен. Проверьте, что бэкенд запущен.'
  }

  const data = response.data ?? {}

  switch (response.status) {
    case 401:
      return 'Нужно войти в аккаунт.'
    case 403:
      return 'Недостаточно прав для этого действия.'
    case 404:
      return 'Не найдено.'
    case 422: {
      const first = Object.values(data.errors ?? {})[0]

      return Array.isArray(first) ? first[0] : (data.message ?? 'Проверьте введённые данные.')
    }
    case 429:
      return 'Слишком много попыток. Подождите немного и попробуйте снова.'
    case 502:
      return data.message ?? 'Внешний сервис не ответил. Попробуйте позже.'
    default:
      return `Ошибка сервера (${response.status}). Попробуйте позже.`
  }
}

export default http
