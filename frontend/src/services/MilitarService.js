import { apiFetchJson, apiFetch } from '../utils/api.js'

export const MilitarService = {
  /**
   * Busca militares com base nos filtros
   * @param {Object} params 
   * @returns {Promise<Array>}
   */
  async search(params = {}) {
    const query = new URLSearchParams(params).toString()
    return apiFetchJson(`/sismil/backend/api/militar/search?${query}`)
  },

  /**
   * @param {number} id 
   * @returns {Promise<Array>}
   */
  async getHistorico(id) {
    return apiFetchJson(`/sismil/backend/api/militar/historico?id=${id}`)
  },

  /**
   * Salva os dados de um militar (pode incluir arquivos, por isso usa FormData)
   * @param {FormData} formData 
   */
  async save(formData) {
    return apiFetchJson('/sismil/backend/api/militar/save', {
      method: 'POST',
      body: formData
    })
  },

  /**
   * @param {FormData} formData 
   */
  async desligar(formData) {
    return apiFetchJson('/sismil/backend/api/militar/desligar', {
      method: 'POST',
      body: formData
    })
  },

  /**
   * @param {Object} payload 
   */
  async reativar(payload) {
    return apiFetchJson('/sismil/backend/api/militar/reativar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },

  async getById(id) {
    return apiFetchJson(`/sismil/backend/api/militar/get?id=${id}`)
  },

  async delete(payload) {
    return apiFetchJson('/sismil/backend/api/militar/delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },

  async saveHistorico(formData) {
    return apiFetchJson('/sismil/backend/api/militar/alteracao/save', {
      method: 'POST',
      body: formData
    })
  },

  async deleteHistorico(id) {
    const fd = new FormData()
    fd.append('id', id)
    return apiFetchJson('/sismil/backend/api/militar/alteracao/delete', {
      method: 'POST',
      body: fd
    })
  }
}
