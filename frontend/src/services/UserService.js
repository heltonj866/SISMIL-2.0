import { apiFetchJson } from '@/utils/api'

export const UserService = {
  async getAll() {
    return apiFetchJson('/sismil/backend/api/user/list')
  },
  
  async create(payload) {
    return apiFetchJson('/sismil/backend/api/user/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },
  
  async update(payload) {
    return apiFetchJson('/sismil/backend/api/user/update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },
  
  async delete(id) {
    const formData = new FormData()
    formData.append('id_user', id)
    return apiFetchJson('/sismil/backend/api/user/delete', {
      method: 'POST',
      body: formData
    })
  }
}
