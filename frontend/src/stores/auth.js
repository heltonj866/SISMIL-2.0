import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    role: localStorage.getItem('sismil_role') || null,
    identidade: localStorage.getItem('sismil_idt') || null,
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
    setSession(role, identidade) {
      this.role = role
      this.identidade = identidade
      localStorage.setItem('sismil_role', role)
      if (identidade) localStorage.setItem('sismil_idt', identidade)
    },
    logout() {
      this.role = null
      this.identidade = null
      localStorage.removeItem('sismil_role')
      localStorage.removeItem('sismil_idt')
      fetch('/sismil/backend/logout.php').catch(() => {})
    }
  }
})
