<template>
  <!-- Toasts -->
  <Teleport to="body">
    <div class="toast-container">
      <TransitionGroup name="toast">
        <div v-for="t in toasts" :key="t.id" class="toast-item" :class="'toast-' + t.type">
          <i :class="iconMap[t.type]"></i>
          <span>{{ t.message }}</span>
        </div>
      </TransitionGroup>
    </div>

    <!-- Confirm Modal -->
    <Transition name="fade">
      <div v-if="confirmState.visible" class="confirm-overlay" @click.self="respond(false)">
        <div class="confirm-box">
          <div class="confirm-header" :class="'confirm-' + confirmState.variant">
            <i :class="confirmIcon"></i>
            <h5>{{ confirmState.title }}</h5>
          </div>
          <div class="confirm-body">
            <p>{{ confirmState.message }}</p>
          </div>
          <div class="confirm-footer">
            <button class="cbtn cbtn-cancel" @click="respond(false)">{{ confirmState.cancelText }}</button>
            <button class="cbtn" :class="'cbtn-' + confirmState.variant" @click="respond(true)">
              {{ confirmState.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { useToast, useConfirm } from '../composables/useToast'

const { toasts } = useToast()
const { confirmState, respond } = useConfirm()

const iconMap = {
  success: 'fas fa-check-circle',
  error: 'fas fa-times-circle',
  warning: 'fas fa-exclamation-triangle',
  info: 'fas fa-info-circle'
}

const confirmIcon = computed(() => {
  const map = { danger: 'fas fa-exclamation-triangle', warning: 'fas fa-question-circle', info: 'fas fa-info-circle' }
  return map[confirmState.value.variant] || map.danger
})
</script>

<style>
/* Toast container */
.toast-container {
  position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999;
  display: flex; flex-direction: column; gap: 0.6rem; max-width: 380px;
}
.toast-item {
  display: flex; align-items: center; gap: 0.7rem;
  padding: 0.85rem 1.25rem; border-radius: 10px;
  font-size: 0.88rem; font-weight: 600; color: white;
  box-shadow: 0 8px 24px rgba(0,0,0,0.18);
  backdrop-filter: blur(8px);
}
.toast-success { background: linear-gradient(135deg, #16a34a, #22c55e); }
.toast-error   { background: linear-gradient(135deg, #dc2626, #ef4444); }
.toast-warning { background: linear-gradient(135deg, #d97706, #f59e0b); }
.toast-info    { background: linear-gradient(135deg, #0284c7, #38bdf8); }
.toast-item i  { font-size: 1.15rem; flex-shrink: 0; }

/* Toast transitions */
.toast-enter-active { animation: toastIn 0.35s ease; }
.toast-leave-active { animation: toastOut 0.3s ease; }
@keyframes toastIn  { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
@keyframes toastOut { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(40px); } }

/* Confirm overlay */
.confirm-overlay {
  position: fixed; inset: 0; z-index: 10000;
  background: rgba(0,0,0,0.5); backdrop-filter: blur(3px);
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.confirm-box {
  background: white; border-radius: 14px; width: 100%; max-width: 420px;
  overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.25);
  animation: confirmPop 0.25s ease;
}
@keyframes confirmPop { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
.confirm-header {
  display: flex; align-items: center; gap: 0.65rem;
  padding: 1.15rem 1.5rem; color: white;
}
.confirm-header h5 { margin: 0; font-weight: 700; font-size: 1rem; }
.confirm-header i  { font-size: 1.3rem; }
.confirm-danger  { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.confirm-warning { background: linear-gradient(135deg, #d97706, #b45309); }
.confirm-info    { background: linear-gradient(135deg, #0284c7, #0369a1); }

.confirm-body { padding: 1.5rem; }
.confirm-body p { margin: 0; font-size: 0.92rem; line-height: 1.55; color: #374151; }

.confirm-footer {
  padding: 1rem 1.5rem; background: #f9fafb; border-top: 1px solid #e5e7eb;
  display: flex; justify-content: flex-end; gap: 0.65rem;
}
.cbtn {
  border: none; padding: 0.55rem 1.25rem; border-radius: 8px;
  font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
}
.cbtn-cancel { background: #e5e7eb; color: #374151; }
.cbtn-cancel:hover { background: #d1d5db; }
.cbtn-danger  { background: #dc2626; color: white; }
.cbtn-danger:hover  { background: #b91c1c; }
.cbtn-warning { background: #d97706; color: white; }
.cbtn-warning:hover { background: #b45309; }
.cbtn-info    { background: #0284c7; color: white; }
.cbtn-info:hover    { background: #0369a1; }

/* Fade */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
