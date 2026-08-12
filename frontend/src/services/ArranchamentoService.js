import { apiFetchJson } from '../utils/api.js'

export const ArranchamentoService = {
  /**
   * @param {string} data YYYY-MM-DD
   * @returns {Promise<Array>}
   */
  async getByData(data) {
    return apiFetchJson(`/sismil/backend/api/arranchamento/list?data=${data}`)
  },

  /**
   * @param {Object} payload 
   */
  async save(payload) {
    return apiFetchJson('/sismil/backend/api/arranchamento/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
  },

  /**
   * @param {Object} payload 
   */
  async saveExtra(payload) {
    return apiFetchJson('/sismil/backend/api/arranchamento/saveExtra', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
  }
}
