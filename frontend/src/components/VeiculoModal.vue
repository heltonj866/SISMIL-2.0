<template>
  <div class="modal-overlay" v-if="show">
    <div class="modal-content glass-panel">
      <div class="modal-header">
        <h5>Inspeção Veicular (S2)</h5>
        <button class="btn-close" @click="$emit('close')">&times;</button>
      </div>
      <div class="modal-body" v-if="militar">
        <div class="militar-info mb-4">
          <h6>Proprietário: <strong>{{ militar.posto_grad }} {{ militar.nome_guerra }}</strong></h6>
          <span class="badge badge-secondary">{{ militar.subunidade }}</span>
        </div>

        <div v-if="!militar.placa" class="alert alert-warning">
          Este militar não possui veículo cadastrado.
        </div>
        <div v-else>
          <div class="vehicle-details form-grid basic-grid mb-4">
            <div class="detail-item">
              <label>Tipo</label>
              <div class="val">{{ militar.tipo_veiculo || '---' }}</div>
            </div>
            <div class="detail-item">
              <label>Modelo/Cor</label>
              <div class="val">{{ militar.modelo || '---' }} / {{ militar.cor || '---' }}</div>
            </div>
            <div class="detail-item">
              <label>Placa</label>
              <div class="val placa-badge">{{ militar.placa }}</div>
            </div>
            <div class="detail-item">
              <label>Venc. CRLV</label>
              <div class="val">{{ formatData(militar.validade_crlv) }}</div>
            </div>
            <div class="detail-item">
              <label>Venc. CNH</label>
              <div class="val">{{ formatData(militar.validade_cnh) }}</div>
            </div>
            <div class="detail-item">
              <label>Status Atual</label>
              <div class="val">
                <span class="badge" :class="militar.homologado == 1 ? 'badge-success' : 'badge-warning'">
                  {{ militar.homologado == 1 ? 'LIBERADO (SELO)' : 'PENDENTE' }}
                </span>
              </div>
            </div>
          </div>
          
          <div class="actions-area">
             <button v-if="militar.homologado != 1" class="btn-modern btn-success w-100 mb-2" @click="handleHomologate(1)" :disabled="loading">
               {{ loading ? 'Processando...' : 'Aprovar e Emitir Selo' }}
             </button>
             <button v-if="militar.homologado == 1" class="btn-modern btn-danger-outline w-100" @click="handleHomologate(0)" :disabled="loading">
               Revogar Acesso
             </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { apiFetch } from '../utils/api.js'
import { useToast } from '../composables/useToast'
const { error: toastError } = useToast()

const props = defineProps({
  show: Boolean,
  militar: Object
})
const emit = defineEmits(['close', 'updated'])
const loading = ref(false)

const formatData = (d) => {
  if(!d || d==='0000-00-00') return '---'
  const p = d.split('-')
  return p.length===3 ? `${p[2]}/${p[1]}/${p[0]}` : d
}

const handleHomologate = async (status) => {
  loading.value = true
  try {
    const fd = new FormData()
    fd.append('id_militar', props.militar.id)
    fd.append('homologado', status)
    
    const res = await apiFetch('/sismil/backend/api/veiculo/homologar', {
      method: 'POST',
      body: fd
    })
    
    const json = await res.json().catch(() => ({ status: 'erro', msg: 'Erro ao processar resposta.' }))
    
    if(json.status === 'sucesso') {
      emit('updated')
      emit('close')
    } else {
      toastError('Erro: ' + json.msg)
    }
  } catch(e) {
    toastError('Erro de conexão.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}
.modal-content {
  background: white;
  width: 100%; max-width: 500px;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}
.modal-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #dee2e6;
}
.modal-header h5 { margin: 0; color: var(--primary-blue); font-weight: 700; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.modal-body { padding: 1.5rem; }
.mb-4 { margin-bottom: 1.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
.badge { padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
.badge-secondary { background: #e9ecef; color: #495057; }
.badge-success { background: #d1e7dd; color: #0f5132; }
.badge-warning { background: #fff3cd; color: #664d03; }
.alert-warning { background: #fff3cd; border: 1px solid #ffe69c; color: #664d03; padding: 1rem; border-radius: 8px; text-align: center; }
.form-grid { display: grid; gap: 1rem; }
.basic-grid { grid-template-columns: 1fr 1fr; }
.detail-item label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.2rem; text-transform: uppercase; }
.val { font-size: 0.95rem; font-weight: 500; }
.placa-badge { display: inline-block; background: #f8f9fa; border: 1px solid #ced4da; padding: 0.15rem 0.4rem; border-radius: 4px; font-family: monospace; font-weight: bold; }
.w-100 { width: 100%; }
.btn-success { background: var(--success); color: white; border: none; padding: 0.8rem; border-radius: 8px; font-weight: 600; }
.btn-danger-outline { background: transparent; color: var(--danger); border: 1px solid var(--danger); padding: 0.8rem; border-radius: 8px; font-weight: 600; }
</style>
