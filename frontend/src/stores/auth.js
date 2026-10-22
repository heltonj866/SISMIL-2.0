import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    role: localStorage.getItem('sismil_role') || null,
    identidade: localStorage.getItem('sismil_idt') || null,
    csrfToken: sessionStorage.getItem('sismil_csrf') || null,
    militarId: localStorage.getItem('sismil_militar_id') ? Number(localStorage.getItem('sismil_militar_id')) : null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.role,
    userRole: (state) => state.role,
    isAdmin: (state) => state.role === 'admin',
    isSargenteacao: (state) => state.role === 'sargenteacao',
    isS2: (state) => state.role === 's2',
    isEncMat: (state) => state.role === 'enc_mat',
    isMilitar: (state) => state.role === 'militar',
    canEdit: (state) => ['admin', 'sargenteacao'].includes(state.role),
    canViewS1Panel: (state) => ['admin', 'sargenteacao'].includes(state.role),
    canHomologar: (state) => state.role === 's2',
    canDelete: (state) => state.role === 'admin',
    canViewS2Report: (state) => ['admin', 's2'].includes(state.role),
  },
  actions: {
    setSession(role, identidade, csrfToken = null, militarId = null) {
      this.role = role
      this.identidade = identidade
      this.csrfToken = csrfToken
      this.militarId = militarId ? Number(militarId) : null
      localStorage.setItem('sismil_role', role)
      if (identidade) localStorage.setItem('sismil_idt', identidade)
      if (csrfToken) sessionStorage.setItem('sismil_csrf', csrfToken)
      if (militarId) localStorage.setItem('sismil_militar_id', String(militarId))
      else localStorage.removeItem('sismil_militar_id')
    },
    setCsrfToken(token) {
      this.csrfToken = token
      if (token) sessionStorage.setItem('sismil_csrf', token)
    },
    logout() {
      this.role = null
      this.identidade = null
      this.csrfToken = null
      this.militarId = null
      localStorage.removeItem('sismil_role')
      localStorage.removeItem('sismil_idt')
      localStorage.removeItem('sismil_militar_id')
      sessionStorage.removeItem('sismil_csrf')
      
      import('../services/AuthService.js').then(module => {
        module.AuthService.logout().catch(() => {})
      })
    }
  }
})
