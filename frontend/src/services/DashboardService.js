import { apiFetchJson } from '@/utils/api'

export const DashboardService = {
  /**
   * @returns {Promise<Object>}
   */
  async getStats() {
    return apiFetchJson('/sismil/backend/api/dashboard/stats')
  }
}
