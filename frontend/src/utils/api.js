/**
 * ARQUIVO: frontend/src/utils/api.js
 * Wrapper centralizado para todas as chamadas de API autenticadas.
 *
 * Inclui automaticamente:
 * - credentials: 'include' (envia cookies de sessão)
 * - X-Csrf-Token: <token> (proteção CSRF — HIGH-01)
 *
 * Uso:
 *   import { apiFetch } from '@/utils/api'
 *   const res = await apiFetch('/sismil/backend/save_militar.php', { method: 'POST', body: formData })
 */

import { useAuthStore } from '@/stores/auth'

/**
 * apiFetch — substituto seguro do fetch() para endpoints autenticados.
 * @param {string} url - URL da API
 * @param {RequestInit} options - Opções do fetch (method, body, headers, etc.)
 * @returns {Promise<Response>}
 */
export async function apiFetch(url, options = {}) {
  const authStore = useAuthStore()

  const headers = {
    ...(options.headers || {}),
  }

  // Injeta o token CSRF automaticamente em todas as requisições POST
  if (authStore.csrfToken) {
    headers['X-Csrf-Token'] = authStore.csrfToken
  }

  return fetch(url, {
    ...options,
    headers,
    credentials: 'include', // Garante envio do cookie de sessão
  })
}

/**
 * apiFetchJson — wrapper para respostas JSON, retorna o objeto diretamente.
 * @param {string} url
 * @param {RequestInit} options
 * @returns {Promise<any>}
 */
export async function apiFetchJson(url, options = {}) {
  const res = await apiFetch(url, options)
  return res.json()
}
