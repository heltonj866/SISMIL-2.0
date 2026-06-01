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
          path: 'frota',
          name: 'frota',
          component: () => import('../views/FrotaView.vue')
        },
        {
          path: 'admin',
          name: 'admin',
          component: () => import('../views/AdminView.vue')
        },
        {
          path: 'arranchamento-painel',
          name: 'arranchamento_admin',
          component: () => import('../views/ArranchamentoAdminView.vue')
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
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next('/dashboard')
  } else {
    next()
  }
})

export default router
