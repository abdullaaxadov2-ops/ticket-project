import http from './http'

export const profileApi = {
  async changeName(name) {
    const { data } = await http.patch('/me/name', { name })

    return data.data
  },

  // payload: { current_password, new_password, new_password_confirmation }
  async changePassword(payload) {
    await http.patch('/me/password', payload)
  },
}
