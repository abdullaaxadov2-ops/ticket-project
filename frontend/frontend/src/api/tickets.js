import http from './http'

export const ticketsApi = {
  async list(page = 1) {
    const { data } = await http.get('/tickets', { params: { page } })

    return data
  },

  async show(id) {
    const { data } = await http.get(`/tickets/${id}`)

    return data.data
  },
}
