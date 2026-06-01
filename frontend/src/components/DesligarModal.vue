<template>
  <!-- Modal de desligamento - requer PDF do Nada Consta (igual ao legado) -->
  <div class="modal-overlay" v-if="show" @click.self="$emit('close')">
    <div class="modal-content glass-panel">
      <div class="modal-header">
        <h5><i class="fas fa-user-slash text-danger"></i> Desligamento de Militar</h5>
        <button class="btn-close" @click="$emit('close')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="alert-danger mb-3">
          <strong>Atenção:</strong> Você está prestes a desligar o militar
          <strong>{{ militar?.posto_grad }} {{ militar?.nome_guerra }}</strong>.
          O histórico e todos os dados serão mantidos.
          <br><br>
          <strong>É obrigatório anexar o Nada Consta/Deve (PDF).</strong>
        </div>

        <div class="input-group">
          <label class="text-danger fw-bold">
            <i class="fas fa-file-pdf"></i> Anexar Ficha de Nada Deve (PDF) *
          </label>
          <input type="file" @change="handleFile" accept="application/pdf" class="input-modern" ref="fileInput">
          <small class="text-muted mt-1 d-block" v-if="!arquivo">Obrigatório para confirmar o desligamento.</small>
          <small class="text-success mt-1 d-block" v-else>
            <i class="fas fa-check-circle"></i> {{ arquivo.name }} selecionado.
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-modern btn-secondary-outline" @click="$emit('close')">Cancelar</button>
        <button class="btn-modern btn-danger" @click="confirmar" :disabled="loading || !arquivo">
          <i class="fas fa-user-slash"></i>
          {{ loading ? 'Processando...' : 'Confirmar Desligamento' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useToast } from '../composables/useToast'
const { warning: toastWarning, error: toastError } = useToast()

const props = defineProps({ show: Boolean, militar: Object })
const emit = defineEmits(['close', 'done'])

const arquivo = ref(null)
const loading = ref(false)
const fileInput = ref(null)

const handleFile = (e) => {
  const f = e.target.files[0]
  if (!f) return
  if (f.type !== 'application/pdf') {
    toastWarning('O arquivo deve ser no formato PDF.')
    if (fileInput.value) fileInput.value.value = ''
    arquivo.value = null
    return
  }
  arquivo.value = f
}

const confirmar = async () => {
  if (!arquivo.value) {
    toastWarning('Obrigatório anexar o PDF do Nada Deve para prosseguir.')
    return
  }
  loading.value = true
  const fd = new FormData()
  fd.append('militar_id', props.militar.id)
  fd.append('nada_consta', arquivo.value)
  try {
    const res = await fetch('/sismil/backend/desligar_militar.php', { method: 'POST', body: fd })
    const json = await res.json()
    if (json.status === 'sucesso') {
      arquivo.value = null
      if (fileInput.value) fileInput.value.value = ''
      emit('done')
    } else {
      toastError('Erro: ' + json.msg)
    }
  } catch (e) {
    toastError('Erro de conexão.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); display:flex; align-items:center; justify-content:center; z-index:2000; padding:1rem; }
.modal-content { background:white; width:100%; max-width:500px; border-radius:var(--radius-lg); overflow:hidden; }
.modal-header { padding:1rem 1.5rem; background:#f8f9fa; border-bottom:1px solid #dee2e6; display:flex; justify-content:space-between; align-items:center; }
.modal-header h5 { margin:0; font-weight:700; }
.btn-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted); line-height:1; }
.modal-body { padding:1.5rem; }
.modal-footer { padding:1rem 1.5rem; background:#f8f9fa; border-top:1px solid #dee2e6; display:flex; justify-content:flex-end; gap:0.75rem; }

.alert-danger { background:#f8d7da; border:1px solid #f5c2c7; color:#842029; border-radius:8px; padding:1rem; font-size:0.9rem; }
.input-group label { display:block; font-size:0.85rem; font-weight:600; margin-bottom:0.5rem; }
.input-group { margin-top:1rem; }
.text-danger { color:var(--danger); }
.text-success { color:var(--success); }
.text-muted { color:var(--text-muted); }
.fw-bold { font-weight:700; }
.mt-1 { margin-top:0.25rem; }
.mb-3 { margin-bottom:1rem; }
.d-block { display:block; }
.btn-danger { background:var(--danger); color:white; border:none; }
.btn-danger:hover:not(:disabled) { background:#bb2d3b; }
.btn-danger:disabled { opacity:0.6; cursor:not-allowed; }
</style>
