<template>
  <div class="vehicle-list">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="section-title mb-0">Frota / Veículos Cadastrados</h5>
      <!-- Botão Adicionar: somente admin/sargenteacao -->
      <button v-if="canEdit && militarId" type="button" class="btn-modern btn-success btn-sm" @click="showForm = true">
        <i class="fas fa-plus"></i> Novo Veículo
      </button>
    </div>

    <div v-if="!militarId" class="alert alert-warning">
      Salve os dados básicos do militar primeiro para gerenciar a frota.
    </div>

    <!-- Tabela de Veículos -->
    <div class="table-responsive" v-if="militarId">
      <table class="table-modern">
        <thead>
          <tr>
            <th>Tipo</th><th>Placa</th><th>Marca / Modelo</th><th>Cor</th>
            <th>Emissão CRLV</th><th>CRLV</th><th>Status S2</th><th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="v in veiculos" :key="v.id">
            <td class="fw-bold text-secondary">{{ v.tipo_veiculo }}</td>
            <td>
              <span class="placa-badge">{{ v.placa }}</span>
            </td>
            <td>{{ v.marca ? v.marca + ' / ' : '' }}{{ v.modelo }}</td>
            <td>{{ v.cor }}</td>
            <td>{{ formatData(v.emissao_crlv) }}</td>
            <td>
              <!-- CRLV visivel para todos -->
              <a v-if="v.pdf_veiculo"
                :href="`/sismil/uploads/documentos/${v.pdf_veiculo}`"
                target="_blank"
                class="btn-ver-doc"
                title="Abrir CRLV"
              >
                <i class="fas fa-file-pdf"></i> Ver CRLV
              </a>
              <span v-else class="sem-doc">—</span>
            </td>
            <td>
              <span class="badge" :class="v.homologado == 1 ? 'badge-success' : 'badge-warning'">
                {{ v.homologado == 1 ? 'HOMOLOGADO' : 'PENDENTE S2' }}
              </span>
              <div class="text-danger small fw-bold mt-1" v-if="v.observacao_s2" style="line-height:1.1; font-size: 0.75rem;">
                <i class="fas fa-exclamation-triangle"></i> {{ v.observacao_s2 }}
              </div>
            </td>
            <td>
              <!-- admin/sargenteacao: editar e excluir -->
              <template v-if="canEdit">
                <button type="button" class="btn-action-edit" @click="editarVeiculo(v)" title="Editar Veículo">
                  <i class="fas fa-edit"></i> Editar
                </button>
                <button type="button" class="btn-action-del" @click="excluir(v.id)" title="Excluir Veículo">
                  <i class="fas fa-trash"></i>
                </button>
              </template>
              <!-- s2/transporte: botoes visiveis de avaliar e imprimir -->
              <template v-else-if="canHomologar">
                <div class="s2-actions">
                  <button type="button" class="btn-s2-action btn-avaliar" @click="avaliarVeiculo(v)">
                    <i class="fas fa-stamp"></i> Avaliar
                  </button>
                  <button type="button" class="btn-s2-action btn-selo" @click="imprimirSelo(v.id)">
                    <i class="fas fa-print"></i> Imprimir Selo
                  </button>
                </div>
              </template>
            </td>
          </tr>
          <tr v-if="veiculos.length === 0">
            <td colspan="8" class="text-center text-muted py-3">Nenhum veículo cadastrado para este militar.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Formulário de Novo/Edição de Veículo (só admin/sargenteacao) -->
    <div v-if="showForm && canEdit" class="form-veiculo-panel">
      <h6 class="mb-3">{{ form.id ? 'Editar Veículo' : 'Adicionar Veículo' }}</h6>
        <div class="form-grid veiculo-grid">
        <div class="input-group">
          <label>Tipo</label>
          <select v-model="form.tipo" class="input-modern">
            <option>Carro</option><option>Moto</option>
          </select>
        </div>
        <div class="input-group">
          <label>Placa</label>
          <input type="text" v-model="form.placa" class="input-modern text-uppercase" required placeholder="AAA-0000">
        </div>
        <div class="input-group">
          <label>Cor</label>
          <input type="text" v-model="form.cor" class="input-modern" required placeholder="Ex: Prata">
        </div>
        <div class="input-group">
          <label>Marca</label>
          <input type="text" v-model="form.marca" class="input-modern" placeholder="Ex: Toyota">
        </div>
        <div class="input-group">
          <label>Modelo</label>
          <input type="text" v-model="form.modelo" class="input-modern" required placeholder="Ex: Hilux">
        </div>
        <div class="input-group">
          <label>Emissão do CRLV</label>
          <input type="date" v-model="form.emissao" class="input-modern" title="Data de emissão. Validade = emissão + 1 ano">
        </div>
        <div class="input-group full-width">
          <label>CRLV (PDF)</label>
          <input type="file" @change="handleFile" accept="application/pdf" class="input-modern">
          <small class="text-muted mt-1 d-block" v-if="form.id && veiculos.find(v=>v.id===form.id)?.pdf_veiculo">
            <a :href="`/sismil/uploads/documentos/${veiculos.find(v=>v.id===form.id)?.pdf_veiculo}`" target="_blank" class="doc-link">
              <i class="fas fa-file-pdf"></i> Ver CRLV atual
            </a>
          </small>
        </div>
      </div>
      <div class="d-flex justify-content-end mt-3 gap-2">
        <button type="button" class="btn-modern btn-secondary-outline btn-sm" @click="cancelarForm">Cancelar</button>
        <button type="button" class="btn-modern btn-success btn-sm" @click="salvar" :disabled="loading">
          {{ loading ? 'Salvando...' : 'Salvar Veículo' }}
        </button>
      </div>
    </div>

    <!-- Modal de Avaliação S2 -->
    <div class="modal-overlay" v-if="showAvalModal" @click.self="showAvalModal = false">
      <div class="modal-content glass-panel">
        <div class="modal-header">
          <h5>Avaliação S2</h5>
          <button class="btn-close" @click="showAvalModal = false">&times;</button>
        </div>
        <div class="modal-body">
          <div class="input-group mb-3">
            <label class="fw-bold">Status do Veículo</label>
            <select v-model="avalForm.status" class="input-modern">
              <option value="1">🟢 LIBERADO (Aprovado)</option>
              <option value="0">🔴 PENDENTE / REJEITADO</option>
            </select>
          </div>
          <div class="input-group">
            <label class="fw-bold">Observações (Notificar Militar)</label>
            <textarea v-model="avalForm.obs" class="input-modern" rows="3"
              placeholder="Ex: Falta anexar a CNH, CRLV ilegível, etc..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-modern btn-secondary-outline" @click="showAvalModal = false">Cancelar</button>
          <button class="btn-modern btn-primary" @click="salvarAvaliacao" :disabled="loading">
            Salvar Avaliação
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useToast, useConfirm } from '../composables/useToast'

const { success: toastSuccess, error: toastError, warning: toastWarning } = useToast()
const { ask: confirmDialog } = useConfirm()

const props = defineProps({
  militarId: [Number, String],
  dadosMilitar: Object
})
const authStore = useAuthStore()

const canEdit = computed(() => authStore.canEdit)
const canHomologar = computed(() => authStore.canHomologar)

const veiculos = ref([])
const showForm = ref(false)
const loading = ref(false)

const form = ref({ id: null, tipo: 'Carro', placa: '', cor: '', marca: '', modelo: '', validade: '' })
const file = ref(null)

const showAvalModal = ref(false)
const avalForm = ref({ id: null, militarId: null, status: '1', obs: '' })

const handleFile = (e) => { if (e.target.files.length) file.value = e.target.files[0] }

const formatData = (d) => {
  if (!d || d === '0000-00-00') return '---'
  const p = d.split('-')
  return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d
}

const fetchVeiculos = async () => {
  if (!props.militarId) return
  try {
    const res = await fetch(`/sismil/backend/get_veiculos.php?militar_id=${props.militarId}`)
    const json = await res.json()
    if (json.status === 'sucesso') veiculos.value = json.dados
  } catch (e) { console.error(e) }
}

const cancelarForm = () => {
  showForm.value = false
  form.value = { id: null, tipo: 'Carro', placa: '', cor: '', marca: '', modelo: '', emissao: '' }
  file.value = null
}

const editarVeiculo = (v) => {
  form.value = { id: v.id, tipo: v.tipo_veiculo, placa: v.placa, cor: v.cor, marca: v.marca || '', modelo: v.modelo, emissao: v.emissao_crlv || '' }
  showForm.value = true
}

const salvar = async () => {
  if (!form.value.placa || !form.value.modelo) { toastWarning('Placa e modelo são obrigatórios'); return }
  loading.value = true
  const fd = new FormData()
  fd.append('v_militar_id', props.militarId)
  fd.append('v_tipo', form.value.tipo)
  fd.append('v_placa', form.value.placa)
  fd.append('v_cor', form.value.cor)
  fd.append('v_marca', form.value.marca)
  fd.append('v_modelo', form.value.modelo)
  fd.append('v_emissao', form.value.emissao)
  if (form.value.id) fd.append('veiculo_id', form.value.id)
  if (file.value) fd.append('v_pdf', file.value)

  try {
    const res = await fetch('/sismil/backend/save_veiculo.php', { method: 'POST', body: fd })
    const json = await res.json()
    if (json.status === 'sucesso') {
      cancelarForm()
      fetchVeiculos()
    } else { toastError('Erro: ' + json.msg) }
  } catch (e) { toastError('Erro de conexão.') }
  finally { loading.value = false }
}

const excluir = async (id) => {
  const ok = await confirmDialog('Deseja realmente excluir este veículo?', { title: 'Confirmação' })
  if (!ok) return
  try {
    const res = await fetch('/sismil/backend/excluir_veiculo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    })
    const json = await res.json()
    if (json.status === 'sucesso') fetchVeiculos()
    else toastError('Erro: ' + json.msg)
  } catch (e) { console.error(e) }
}

const avaliarVeiculo = (v) => {
  avalForm.value = { id: v.id, militarId: props.militarId, status: v.homologado == 1 ? '1' : '0', obs: v.observacao_s2 || '' }
  showAvalModal.value = true
}

const salvarAvaliacao = async () => {
  loading.value = true
  try {
    const res = await fetch('/sismil/backend/toggle_homolog_veiculo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: avalForm.value.id, status: avalForm.value.status, observacao: avalForm.value.obs })
    })
    const json = await res.json()
    if (json.status === 'sucesso') {
      showAvalModal.value = false
      fetchVeiculos()
    } else { toastError('Erro: ' + json.msg) }
  } catch (e) { toastError('Erro de conexão.') }
  finally { loading.value = false }
}

const imprimirSelo = (id) => {
  const w = 600, h = 400
  const l = (screen.width - w) / 2, t = (screen.height - h) / 2
  window.open(`/sismil/backend/print_selo.php?veiculo_id=${id}`, 'ImprimirSelo', `width=${w},height=${h},top=${t},left=${l},scrollbars=yes`)
}

onMounted(fetchVeiculos)
watch(() => props.militarId, fetchVeiculos)
</script>

<style scoped>
.section-title { font-size: 1.1rem; color: var(--primary-blue); font-weight: 700; }
.table-modern { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.table-modern th, .table-modern td { padding: 0.75rem; border-bottom: 1px solid #dee2e6; text-align: left; font-size: 0.88rem; }
.table-modern th { background: #f8f9fa; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 0.78rem; }
.placa-badge { background: white; border: 2px solid #333; border-radius: 4px; padding: 0.1rem 0.4rem; font-weight: 800; font-family: monospace; font-size: 0.95rem; text-transform: uppercase; }
.badge { padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
.badge-success { background: #d1e7dd; color: #0f5132; }
.badge-warning { background: #fff3cd; color: #664d03; }
.alert-warning { background: #fff3cd; border: 1px solid #ffe69c; color: #664d03; padding: 1rem; border-radius: 8px; text-align: center; margin-top: 1rem; }
.form-grid { display: grid; gap: 1rem; }
.veiculo-grid { grid-template-columns: repeat(3, 1fr); }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.4rem; }
.full-width { grid-column: span 3; }
.form-new { border: 1px solid #e9ecef; border-radius: 8px; margin-top: 1rem; }
.gap-2 { gap: 0.5rem; }
.d-flex { display: flex; }
.justify-content-between { justify-content: space-between; }
.justify-content-end { justify-content: flex-end; }
.align-items-center { align-items: center; }
.btn-icon { background: none; border: none; cursor: pointer; opacity: 0.75; font-size: 1rem; padding: 0.2rem 0.3rem; transition: opacity 0.2s; }
.btn-icon:hover { opacity: 1; }

/* Botões Editar/Excluir Veículo */
.btn-action-edit {
  display: inline-flex; align-items: center; gap: 0.3rem;
  background: #e7f1ff; color: var(--primary-blue); border: 1px solid #b8d4f8;
  padding: 0.25rem 0.6rem; border-radius: 5px; font-size: 0.8rem; font-weight: 700;
  cursor: pointer; transition: all 0.2s; margin-right: 0.3rem;
}
.btn-action-edit:hover { background: var(--primary-blue); color: white; }
.btn-action-del {
  display: inline-flex; align-items: center; gap: 0.3rem;
  background: #f8d7da; color: #842029; border: 1px solid #f5c2c7;
  padding: 0.25rem 0.5rem; border-radius: 5px; font-size: 0.8rem;
  cursor: pointer; transition: all 0.2s;
}
.btn-action-del:hover { background: #dc3545; color: white; }

/* Painel do formulário de veículo */
.form-veiculo-panel {
  background: #f8fbff; border: 2px solid var(--primary-blue);
  border-radius: 10px; padding: 1.25rem; margin-top: 1rem;
}

/* Botões S2 Prominentes */
.s2-actions { display: flex; flex-direction: column; gap: 0.3rem; min-width: 130px; }
.btn-s2-action {
  display: flex; align-items: center; gap: 0.4rem;
  border: none; border-radius: 6px; padding: 0.35rem 0.65rem;
  font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: all 0.2s;
  width: 100%;
}
.btn-avaliar { background: #fff3cd; color: #664d03; border: 1px solid #ffc107; }
.btn-avaliar:hover { background: #ffc107; color: #000; }
.btn-selo { background: #cff4fc; color: #055160; border: 1px solid #9eeaf9; }
.btn-selo:hover { background: #0dcaf0; color: #000; }
.text-primary { color: var(--primary-blue); }
.text-warning { color: #856404; }
.text-dark { color: #212529; }
.text-danger { color: var(--danger); }
.text-muted { color: var(--text-muted); }
.fw-bold { font-weight: 700; }
.mb-3 { margin-bottom: 1rem; }
.mt-1 { margin-top: 0.25rem; }
.mt-3 { margin-top: 1rem; }
.d-block { display: block; }

.doc-link {
  display: inline-flex; align-items: center; gap: 0.35rem;
  background: #f8d7da; color: #842029; border: 1px solid #f5c2c7;
  padding: 0.3rem 0.7rem; border-radius: 5px; font-size: 0.82rem; font-weight: 600;
  text-decoration: none; transition: all 0.2s;
}
.doc-link:hover { background: var(--danger); color: white; }

/* Botão Ver CRLV/CNH visível */
.btn-ver-doc {
  display: inline-flex; align-items: center; gap: 0.3rem;
  background: #f8d7da; color: #842029; border: 1px solid #f5c2c7;
  padding: 0.25rem 0.6rem; border-radius: 5px; font-size: 0.8rem; font-weight: 700;
  text-decoration: none; transition: all 0.2s; white-space: nowrap;
}
.btn-ver-doc:hover { background: #dc3545; color: white; }
.sem-doc { color: #adb5bd; font-size: 1rem; }

/* Modal Avaliação */
.modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); display:flex; align-items:center; justify-content:center; z-index:2000; padding:1rem; }
.modal-content { background: white; width: 100%; max-width: 500px; border-radius: var(--radius-lg); overflow: hidden; }
.modal-header { padding: 1rem 1.5rem; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
.modal-header h5 { margin: 0; font-weight: 700; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1rem 1.5rem; background: #f8f9fa; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 0.75rem; }
</style>
