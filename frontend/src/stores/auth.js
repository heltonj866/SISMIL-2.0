import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    role: localStorage.getItem('sismil_role') || null,
    identidade: localStorage.getItem('sismil_idt') || null,
    csrfToken: null, // HIGH-01: Token CSRF armazenado em memória (não no localStorage)
  }),
  getters: {
    isAuthenticated: (state) => !!state.role,
    userRole: (state) => state.role,
    isAdmin: (state) => state.role === 'admin',
    isSargenteacao: (state) => state.role === 'sargenteacao',
    isS2: (state) => state.role === 's2',
    isEncMat: (state) => state.role === 'enc_mat',
    // Pode editar fichas e lançar S1
    canEdit: (state) => ['admin', 'sargenteacao'].includes(state.role),
    // Pode homologar veículos e imprimir selos
    canHomologar: (state) => state.role === 's2',
    // Pode excluir militares permanentemente
    canDelete: (state) => state.role === 'admin',
    // Pode ver relatório S2
    canViewS2Report: (state) => ['admin', 's2'].includes(state.role),
  },
  actions: {
    setSession(role, identidade, csrfToken = null) {
      this.role = role
      this.identidade = identidade
      this.csrfToken = csrfToken // Armazena token CSRF na memória
      localStorage.setItem('sismil_role', role)
      if (identidade) localStorage.setItem('sismil_idt', identidade)
    },
    setCsrfToken(token) {
      this.csrfToken = token
    },
    logout() {
      this.role = null
      this.identidade = null
      this.csrfToken = null
      localStorage.removeItem('sismil_role')
      localStorage.removeItem('sismil_idt')
      
      import('../services/AuthService.js').then(module => {
        module.AuthService.logout().catch(() => {})
      })
    }
  }
})
