import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Intercept window.fetch to inject CSRF token and credentials for backend APIs
const originalFetch = window.fetch
window.fetch = async (input, init = {}) => {
  let url = ''
  if (typeof input === 'string') {
    url = input
  } else if (input instanceof URL) {
    url = input.href
  } else if (input && typeof input === 'object' && 'url' in input) {
    url = input.url
  }

  // Intercept requests directed to the SISMIL backend endpoints
  const isBackend = url.startsWith('/sismil/backend/') || 
                    url.startsWith('backend/') || 
                    url.includes('/sismil/backend/')

  if (isBackend) {
    const authStore = useAuthStore(pinia)
    
    // Automatically include cookies/session
    init.credentials = 'include'
    
    if (!init.headers) {
      init.headers = {}
    }

    if (authStore.csrfToken) {
      if (init.headers instanceof Headers) {
        init.headers.set('X-Csrf-Token', authStore.csrfToken)
      } else if (Array.isArray(init.headers)) {
        const idx = init.headers.findIndex(h => h[0].toLowerCase() === 'x-csrf-token')
        if (idx !== -1) {
          init.headers[idx][1] = authStore.csrfToken
        } else {
          init.headers.push(['X-Csrf-Token', authStore.csrfToken])
        }
      } else {
        init.headers['X-Csrf-Token'] = authStore.csrfToken
      }
    }
  }

  return originalFetch(input, init)
}

app.mount('#app')
