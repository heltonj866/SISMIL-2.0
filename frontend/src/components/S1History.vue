<template>
  <div class="s1-history">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="section-title mb-0">Ficha Disciplinar (Histórico S1)</h5>
      <button type="button" class="btn-modern btn-primary btn-sm" @click="showForm = true" :disabled="!militarId">
        <i class="fas fa-plus"></i> Novo Registro
      </button>
    </div>

    <div v-if="!militarId" class="alert alert-warning">
      Salve os dados básicos do militar primeiro para lançar alterações.
    </div>

    <!-- Resumo (Dashboard) -->
    <div class="summary-cards" v-if="militarId && !showForm && historico.length > 0">
      <div class="summary-card card-elogio">
        <div class="summary-icon"><i class="fas fa-medal"></i></div>
        <div class="summary-info">
          <h4>{{ totalElogios }}</h4>
          <span>Elogios / FO+</span>
        </div>
      </div>
      <div class="summary-card card-punicao">
        <div class="summary-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="summary-info">
          <h4>{{ totalPunicoes }}</h4>
          <span>Punições / FO-</span>
        </div>
      </div>
      <div class="summary-card card-dias">
        <div class="summary-icon"><i class="fas fa-calendar-times"></i></div>
        <div class="summary-info">
          <h4>{{ totalDiasPrisao }}</h4>
          <span>Dias de Punição</span>
        </div>
      </div>
    </div>

    <!-- Tabela -->
    <div class="table-responsive" v-if="militarId && !showForm">
      <table class="table-modern">
        <thead>
          <tr>
            <th>Data</th><th>Categoria</th><th>Evento</th><th>Descrição</th><th>Doc. Ref</th><th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in historico" :key="h.id">
            <td>{{ formatData(h.data_fato) }}</td>
            <td><span class="badge badge-secondary">{{ h.categoria }}</span></td>
            <td class="fw-bold" :class="h.tipo_detalhe === 'FO+' ? 'text-success' : (h.tipo_detalhe === 'FO-' ? 'text-danger' : '')">
              {{ h.tipo_detalhe }}
            </td>
            <td style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" :title="h.descricao">
              {{ h.descricao }}
            </td>
            <td>{{ h.documento_ref }}</td>
            <td>
              <button type="button" class="btn-icon text-danger" @click="excluir(h.id)">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
          <tr v-if="historico.length === 0">
            <td colspan="6" class="text-center text-muted py-3">Nenhum registro encontrado.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Form -->
    <div v-if="showForm" class="glass-panel p-3 form-new">
      <h6 class="mb-3">Nova Alteração</h6>
      <div class="form-grid basic-grid">
        <div class="input-group">
          <label>Categoria</label>
          <select v-model="form.cat" class="input-modern" required>
            <option value="SAUDE">Saúde</option>
            <option value="DISCIPLINA">Disciplina</option>
            <option value="ELOGIO">Justiça / Elogio</option>
            <option value="ACIDENTE">Acidente</option>
          </select>
        </div>
        <div class="input-group">
          <label>Tipo (Ex: FO+, FO-, Detenção)</label>
          <input type="text" v-model="form.tipo" class="input-modern" required>
        </div>
        <div class="input-group">
          <label>Data</label>
          <input type="date" v-model="form.data" class="input-modern" required>
        </div>
        <div class="input-group">
          <label>Dias (Qtd)</label>
          <input type="number" v-model="form.dias" class="input-modern">
        </div>
        <div class="input-group full-width">
          <label>Documento Referência (BI, BG, Sind...)</label>
          <input type="text" v-model="form.doc" class="input-modern">
        </div>
        <div class="input-group full-width">
          <label>Descrição Detalhada</label>
          <textarea v-model="form.desc" class="input-modern" rows="2"></textarea>
        </div>
        <div class="input-group full-width">
          <label>Anexo (Opcional)</label>
          <input type="file" @change="handleFile" class="input-modern">
        </div>
      </div>
      <div class="d-flex justify-content-end mt-3 gap-2">
        <button type="button" class="btn-modern btn-secondary-outline btn-sm" @click="showForm = false">Cancelar</button>
        <button type="button" class="btn-modern btn-success btn-sm" @click="salvar" :disabled="loading">
          {{ loading ? 'Salvando...' : 'Registrar Alteração' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useToast, useConfirm } from '../composables/useToast'
import { MilitarService } from '@/services/MilitarService'

const { warning: toastWarning, error: toastError } = useToast()
const { ask: confirmDialog } = useConfirm()

const props = defineProps({ militarId: [Number, String] })
const historico = ref([])
const showForm = ref(false)
const loading = ref(false)

const totalElogios = computed(() => {
  return historico.value.filter(h => 
    h.categoria === 'ELOGIO' || 
    (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('FO+')) ||
    (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('ELOGIO'))
  ).length
})

const totalPunicoes = computed(() => {
  return historico.value.filter(h => 
    h.categoria === 'DISCIPLINA' && (
      (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('FO-')) ||
      (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('PRISÃO')) ||
      (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('PRISAO')) ||
      (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('DETENÇÃO')) ||
      (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('DETENCAO')) ||
      (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('REPREENSÃO')) ||
      (h.tipo_detalhe && h.tipo_detalhe.toUpperCase().includes('REPREENSAO'))
    )
  ).length
})

const totalDiasPrisao = computed(() => {
  return historico.value.reduce((acc, h) => {
    return acc + (parseInt(h.qtd_dias) || 0)
  }, 0)
})

const form = ref({ cat: 'DISCIPLINA', tipo: '', data: '', dias: 0, doc: '', desc: '' })
const file = ref(null)

const handleFile = (e) => { if(e.target.files.length) file.value = e.target.files[0] }

const formatData = (d) => {
  if(!d || d==='0000-00-00') return '---'
  const p = d.split('-')
  return p.length===3 ? `${p[2]}/${p[1]}/${p[0]}` : d
}

const fetchHistorico = async () => {
  if(!props.militarId) return
  try {
    const json = await MilitarService.getHistorico(props.militarId)
    if(json.status === 'sucesso') historico.value = json.dados || []
    else historico.value = json || []
  } catch(e) { console.error(e) }
}

const salvar = async () => {
  if(!form.value.tipo || !form.value.data) { toastWarning('Tipo e data são obrigatórios'); return }
  loading.value = true
  const fd = new FormData()
  fd.append('s1_militar_id', props.militarId)
  fd.append('s1_cat', form.value.cat)
  fd.append('s1_tipo', form.value.tipo)
  fd.append('s1_data', form.value.data)
  fd.append('s1_dias', form.value.dias)
  fd.append('s1_doc', form.value.doc)
  fd.append('s1_desc', form.value.desc)
  if(file.value) fd.append('s1_file', file.value)

  try {
    const json = await MilitarService.saveHistorico(fd)
    if(json.status === 'sucesso' || json.msg?.includes('sucesso')) {
      showForm.value = false
      form.value = { cat: 'DISCIPLINA', tipo: '', data: '', dias: 0, doc: '', desc: '' }
      file.value = null
      fetchHistorico()
    } else { toastError('Erro: ' + (json.msg || 'Erro desconhecido')) }
  } catch(e) { toastError('Erro de conexão.') }
  finally { loading.value = false }
}

const excluir = async (id) => {
  const ok = await confirmDialog('Excluir este registro permanentemente?', { title: 'Confirmação' })
  if(ok) {
    try {
      await MilitarService.deleteHistorico(id)
      fetchHistorico()
    } catch(e) {}
  }
}

onMounted(fetchHistorico)
watch(() => props.militarId, fetchHistorico)
</script>

<style scoped>
.section-title { font-size: 1.1rem; color: var(--primary-blue); font-weight: 700; }
.table-modern { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.table-modern th, .table-modern td { padding: 0.8rem; border-bottom: 1px solid #dee2e6; text-align: left; font-size: 0.9rem; }
.table-modern th { background: #f8f9fa; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 0.8rem; }
.badge { padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
.badge-secondary { background: #e9ecef; color: #495057; }
.text-success { color: var(--success); }
.text-danger { color: var(--danger); }
.alert-warning { background: #fff3cd; border: 1px solid #ffe69c; color: #664d03; padding: 1rem; border-radius: 8px; text-align: center; margin-top: 1rem; }
.form-grid { display: grid; gap: 1rem; }
.basic-grid { grid-template-columns: repeat(2, 1fr); }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.4rem; }
.full-width { grid-column: span 2; }
.form-new { border: 1px solid #e9ecef; border-radius: 8px; margin-top: 1rem; }
.gap-2 { gap: 0.5rem; }
.d-flex { display: flex; }
.justify-content-between { justify-content: space-between; }
.align-items-center { align-items: center; }
.justify-content-end { justify-content: flex-end; }
.btn-icon { background: none; border: none; cursor: pointer; opacity: 0.7; transition: opacity 0.2s; }
.btn-icon:hover { opacity: 1; }

.summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
.summary-card { display: flex; align-items: center; padding: 1rem; border-radius: 8px; background: white; border: 1px solid #e9ecef; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.summary-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 1rem; }
.summary-info h4 { margin: 0; font-size: 1.5rem; font-weight: 800; color: #212529; }
.summary-info span { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }

.card-elogio .summary-icon { background: #d1e7dd; color: #0f5132; }
.card-elogio { border-bottom: 3px solid #198754; }

.card-punicao .summary-icon { background: #f8d7da; color: #842029; }
.card-punicao { border-bottom: 3px solid #dc3545; }

.card-dias .summary-icon { background: #fff3cd; color: #664d03; }
.card-dias { border-bottom: 3px solid #ffc107; }

@media (max-width: 768px) {
  .summary-cards { grid-template-columns: 1fr; }
}
</style>
