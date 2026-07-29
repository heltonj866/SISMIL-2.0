import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    role: localStorage.getItem('sismil_role') || null,
    identidade: localStorage.getItem('sismil_idt') || null,
    csrfToken: sessionStorage.getItem('sismil_csrf') || null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.role,
    userRole: (state) => state.role,
    isAdmin: (state) => state.role === 'admin',
    isSargenteacao: (state) => state.role === 'sargenteacao',
    isS2: (state) => state.role === 's2',
    isEncMat: (state) => state.role === 'enc_mat',
    canEdit: (state) => ['admin', 'sargenteacao'].includes(state.role),
    canHomologar: (state) => state.role === 's2',
    canDelete: (state) => state.role === 'admin',
    canViewS2Report: (state) => ['admin', 's2'].includes(state.role),
  },
  actions: {
    setSession(role, identidade, csrfToken = null) {
      this.role = role
      this.identidade = identidade
      this.csrfToken = csrfToken
      localStorage.setItem('sismil_role', role)
      if (identidade) localStorage.setItem('sismil_idt', identidade)
      if (csrfToken) sessionStorage.setItem('sismil_csrf', csrfToken)
    },
    setCsrfToken(token) {
      this.csrfToken = token
      if (token) sessionStorage.setItem('sismil_csrf', token)
    },
    logout() {
      this.role = null
      this.identidade = null
      this.csrfToken = null
      localStorage.removeItem('sismil_role')
      localStorage.removeItem('sismil_idt')
      sessionStorage.removeItem('sismil_csrf')
      
      import('../services/AuthService.js').then(module => {
        module.AuthService.logout().catch(() => {})
      })
    }
  }
})
