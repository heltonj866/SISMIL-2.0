import { apiFetchJson } from '../utils/api.js'

/**
 * @typedef {Object} LoginResponse
 * @property {string} username
 * @property {string} role
 * @property {string} csrf_token
 */

export const AuthService = {
  /**
   * @param {string} username 
   * @param {string} password 
   * @returns {Promise<LoginResponse>}
   */
  async login(username, password) {
    const formData = new FormData()
    formData.append('username', username)
    formData.append('password', password)

    return apiFetchJson('/sismil/backend/api/auth/login', {
      method: 'POST',
      body: formData
    })
  },

  async logout() {
    return apiFetchJson('/sismil/backend/api/auth/logout', {
      method: 'POST'
    })
  },

  async check() {
    return apiFetchJson('/sismil/backend/api/auth/check')
  }
}
