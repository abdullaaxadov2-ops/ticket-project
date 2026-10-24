import http from './http'

export const ordersApi = {
  // items: [{ ticket_type_id, quantity }]
  async create(eventId, items) {
    const { data } = await http.post(`/events/${eventId}/orders`, { items })

    return data.data
  },

  async list(page = 1) {
    const { data } = await http.get('/orders', { params: { page } })

    return data
  },

  async show(id) {
    const { data } = await http.get(`/orders/${id}`)

    return data.data
  },

  // Повторный вызов для того же заказа возвращает ту же ссылку, второй платёж не создаётся
  async pay(id) {
    const { data } = await http.post(`/orders/${id}/pay`)

    return data.data.payment_url
  },
}
