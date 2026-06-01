import { ref } from 'vue'

const toasts = ref([])
let toastId = 0

const confirmState = ref({
  visible: false,
  title: '',
  message: '',
  confirmText: 'Confirmar',
  cancelText: 'Cancelar',
  variant: 'danger', // 'danger' | 'warning' | 'info'
  resolve: null
})

export function useToast() {
  const show = (message, type = 'success', duration = 3500) => {
    const id = ++toastId
    toasts.value.push({ id, message, type })
    setTimeout(() => {
      toasts.value = toasts.value.filter(t => t.id !== id)
    }, duration)
  }

  const success = (msg) => show(msg, 'success')
  const error   = (msg) => show(msg, 'error', 5000)
  const warning = (msg) => show(msg, 'warning', 4000)
  const info    = (msg) => show(msg, 'info', 3500)

  return { toasts, show, success, error, warning, info }
}

export function useConfirm() {
  const ask = (message, options = {}) => {
    return new Promise((resolve) => {
      confirmState.value = {
        visible: true,
        title: options.title || 'Confirmação',
        message,
        confirmText: options.confirmText || 'Confirmar',
        cancelText: options.cancelText || 'Cancelar',
        variant: options.variant || 'danger',
        resolve
      }
    })
  }

  const respond = (answer) => {
    if (confirmState.value.resolve) {
      confirmState.value.resolve(answer)
    }
    confirmState.value.visible = false
    confirmState.value.resolve = null
  }

  return { confirmState, ask, respond }
}
