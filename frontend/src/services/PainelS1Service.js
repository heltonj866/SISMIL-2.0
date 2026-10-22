import { apiFetchJson } from '../utils/api.js'

export const PainelS1Service = {
  // --- NOTIFICAÇÕES ---
  async getNotificacoes(apenasNaoLidas = false) {
    const qs = apenasNaoLidas ? '?nao_lidas=1' : ''
    return apiFetchJson(`/sismil/backend/api/notificacoes/list${qs}`)
  },

  async marcarNotificacaoLida(payload = {}) {
    return apiFetchJson('/sismil/backend/api/notificacoes/ler', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },

  async efetivarPreCadastro(payload) {
    return apiFetchJson('/sismil/backend/api/notificacoes/efetivar-precadastro', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },

  // --- PAINEL S1 / PLANILHA GERAL ---
  async getResumoS1(params = {}) {
    const searchParams = new URLSearchParams()
    if (params.subunidade) searchParams.set('subunidade', params.subunidade)
    if (params.dias) searchParams.set('dias', String(params.dias))
    const qs = searchParams.toString() ? `?${searchParams.toString()}` : ''
    return apiFetchJson(`/sismil/backend/api/s1/resumo${qs}`)
  },

  async registrarSituacaoS1(payload) {
    return apiFetchJson('/sismil/backend/api/s1/situacao', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },

  async sincronizarPlanilha(formDataOrJson) {
    if (formDataOrJson instanceof FormData) {
      return apiFetchJson('/sismil/backend/api/s1/planilha/sync', {
        method: 'POST',
        body: formDataOrJson
      })
    }
    return apiFetchJson('/sismil/backend/api/s1/planilha/sync', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formDataOrJson)
    })
  },

  // --- PORTAL DO MILITAR ---
  async getMeuPerfil() {
    return apiFetchJson('/sismil/backend/api/portal/me')
  },

  async salvarDadosBasicos(payload) {
    return apiFetchJson('/sismil/backend/api/portal/dados-basicos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  },

  async registrarApresentacao(payload) {
    return apiFetchJson('/sismil/backend/api/portal/apresentacao', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
  }
}
