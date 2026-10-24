import http from './http'

export const dictionariesApi = {
  async categories() {
    const { data } = await http.get('/categories')

    return data.data
  },

  async venues() {
    const { data } = await http.get('/venues')

    return data.data
  },
}
