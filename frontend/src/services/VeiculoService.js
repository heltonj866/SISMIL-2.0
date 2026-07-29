import { apiFetchJson } from '@/utils/api'

export const VeiculoService = {
  /**
   * @param {number} militarId 
   * @returns {Promise<Array>}
   */
  async getByMilitar(militarId) {
    return apiFetchJson(`/sismil/backend/api/veiculo/list?militar_id=${militarId}`)
  },

  /**
   * @param {FormData} formData 
   */
  async save(formData) {
    return apiFetchJson('/sismil/backend/api/veiculo/save', {
      method: 'POST',
      body: formData
    })
  },

  /**
   * @param {number} id 
   * @param {number} homologado 1 ou 0
   * @param {string} observacao_s2 
   */
  async homologar(id, homologado, observacao_s2 = '') {
    const formData = new FormData()
    formData.append('id', id)
    formData.append('homologado', homologado)
    formData.append('observacao_s2', observacao_s2)

    return apiFetchJson('/sismil/backend/api/veiculo/homologar', {
      method: 'POST',
      body: formData
    })
  },

  /**
   * @param {number} id 
   */
  async delete(id) {
    const formData = new FormData()
    formData.append('id', id)

    return apiFetchJson('/sismil/backend/api/veiculo/delete', {
      method: 'POST',
      body: formData
    })
  }
}
