import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory('/sismil/'),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue')
    },
    {
      path: '/consulta',
      name: 'consulta',
      component: () => import('../views/PublicSearchView.vue')
    },
    {
      path: '/',
      component: () => import('../layouts/MainLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          redirect: '/dashboard'
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('../views/HomeView.vue')
        },
        {
          path: 'militares',
          name: 'militares',
          component: () => import('../views/MilitaresView.vue')
        },
        {
          path: 'painel-s1',
          name: 'painel_s1',
          component: () => import('../views/PainelS1View.vue'),
          meta: { requiresAuth: true, requiresS1: true }
        },
        {
          path: 'meu-painel',
          name: 'meu_painel',
          component: () => import('../views/MeuPainelView.vue'),
          meta: { requiresAuth: true }
        },
        {
          path: 'frota',
          name: 'frota',
          component: () => import('../views/FrotaView.vue')
        },
        {
          path: 'admin',
          name: 'admin',
          component: () => import('../views/AdminView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        },
        {
          path: 'arranchamento-painel',
          name: 'arranchamento_admin',
          component: () => import('../views/ArranchamentoAdminView.vue')
        },
        {
          path: 'auditoria',
          name: 'auditoria',
          component: () => import('../views/AuditView.vue'),
          meta: { requiresAuth: true, requiresAdmin: true }
        }
      ]
    },
    {
      path: '/arranchamento',
      name: 'arranchamento_public',
      component: () => import('../views/ArranchamentoPublicView.vue')
    }
  ]
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (authStore.isAuthenticated && authStore.isMilitar && to.path !== '/meu-painel' && to.path !== '/consulta' && to.path !== '/arranchamento') {
    next('/meu-painel')
  } else if (to.meta.requiresAdmin && !authStore.isAdmin) {
    next(authStore.isMilitar ? '/meu-painel' : '/dashboard')
  } else if (to.meta.requiresS1 && !authStore.canViewS1Panel) {
    next(authStore.isMilitar ? '/meu-painel' : '/dashboard')
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next(authStore.isMilitar ? '/meu-painel' : '/dashboard')
  } else {
    next()
  }
})

export default router
