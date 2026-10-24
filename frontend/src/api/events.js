import http from './http'

// Пустые фильтры не отправляем, чтобы в адресе запроса не было ?search=&category_id=
function cleanParams(params) {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  )
}

const eventCache = new Map()

export const eventsApi = {
  // Возвращает пагинатор Laravel: { data, current_page, last_page, total, ... }
  async list(params = {}) {
    const { data } = await http.get('/events', { params: cleanParams(params) })

    return data
  },

  async show(id) {
    const { data } = await http.get(`/events/${id}`)

    return data.data
  },

  // Для списков заказов и билетов: одно мероприятие запрашиваем один раз.
  // Отменённое или завершённое бэкенд отдаёт участнику как 404, тогда вернётся null
  showCached(id) {
    if (!eventCache.has(id)) {
      eventCache.set(id, this.show(id).catch(() => null))
    }

    return eventCache.get(id)
  },

  async ticketTypes(eventId) {
    const { data } = await http.get(`/events/${eventId}/ticket-types`)

    return data.data
  },
}
