<template>
  <div class="militares-view">
    <header class="page-header d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2>Gestão de Militares</h2>
        <p class="text-muted">Busca, Cadastro e Ficha Completa</p>
      </div>
      <!-- Novo Cadastro: somente admin e sargenteacao -->
      <button v-if="canEdit" class="btn-modern btn-success" @click="handleNovo">
        <i class="fas fa-user-plus"></i> Novo Cadastro
      </button>
    </header>

    <div v-if="!showForm">
      <!-- Painel de Busca -->
      <div class="search-panel glass-panel mb-4">
        <form @submit.prevent="handleSearch" class="search-form-container">
          <!-- Grade de inputs superiores -->
          <div class="search-inputs-grid">
            <div class="input-group">
              <label>Nome / Guerra</label>
              <input type="text" v-model="filters.termo" class="input-modern" placeholder="Digite para buscar...">
            </div>
            <div class="input-group">
              <label>Posto/Grad</label>
              <select v-model="filters.posto" class="input-modern">
                <option value="">Todos</option>
                <option v-for="p in postos" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="input-group">
              <label>Subunidade</label>
              <select v-model="filters.subunidade" class="input-modern">
                <option value="">Todas</option>
                <option value="Cmdo">Cmdo</option>
                <option value="EM">EM</option>
                <option value="PMGu">PMGu</option>
                <option value="Cia E Eqp Mnt">Cia E Eqp Mnt</option>
                <option value="1ª Cia E Cnst">1ª Cia E Cnst</option>
                <option value="Cia C Ap">Cia C Ap</option>
                <option value="2ª Cia E Cnst">2ª Cia E Cnst</option>
                <option value="NPOR">NPOR</option>
                <option value="PTTC">PTTC</option>
              </select>
            </div>
            <div class="input-group">
              <label>Mês Aniversário</label>
              <select v-model="filters.mes_aniversario" class="input-modern">
                <option value="">Todos</option>
                <option value="1">Janeiro</option>
                <option value="2">Fevereiro</option>
                <option value="3">Março</option>
                <option value="4">Abril</option>
                <option value="5">Maio</option>
                <option value="6">Junho</option>
                <option value="7">Julho</option>
                <option value="8">Agosto</option>
                <option value="9">Setembro</option>
                <option value="10">Outubro</option>
                <option value="11">Novembro</option>
                <option value="12">Dezembro</option>
              </select>
            </div>
          </div>

          <!-- Barra inferior de ações e checkboxes -->
          <div class="search-actions-bar">
            <div class="search-checkboxes">
              <!-- Mostrar inativos: somente admin e sargenteacao -->
              <div class="checkbox-wrap" v-if="canEdit">
                <input type="checkbox" v-model="filters.inativos" id="chkInativos">
                <label for="chkInativos">Apenas Desligados</label>
              </div>
              <!-- Mostrar sem foto: somente admin e sargenteacao -->
              <div class="checkbox-wrap" v-if="canEdit">
                <input type="checkbox" v-model="filters.sem_foto" id="chkSemFoto">
                <label for="chkSemFoto">Exibir Sem Foto</label>
              </div>
            </div>

            <div class="search-button-wrap">
              <button type="submit" class="btn-modern btn-primary" :disabled="loading" style="min-width: 150px;">
                <i class="fas fa-search"></i> {{ loading ? 'Buscando...' : 'Buscar' }}
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Resultados -->
      <div class="results-area" v-if="hasSearched">
        <div class="results-header">
          <h5>Resultados <span class="badge badge-secondary">{{ searchResults.length }}</span></h5>
          <div class="d-flex gap-2" v-if="canEdit">
            <button class="btn-modern btn-secondary-outline btn-sm" @click="handleExportar">
              <i class="fas fa-file-excel"></i> Exportar Lista
            </button>
          </div>
        </div>

        <div class="results-grid">
          <MilitarCard
            v-for="m in searchResults"
            :key="m.id"
            :militar="m"
            @editar="handleEdit"
            @desligar="abrirDesligar"
            @reativar="handleReativar"
            @inspecionar="handleInspecionar"
            @verFicha="abrirModalLeitura"
            @resumo="abrirResumo"
          />
          <div v-if="searchResults.length === 0" class="no-results">
            Nenhum registro encontrado.
          </div>
        </div>
      </div>
    </div>

    <!-- Formulário completo (admin/sargenteacao/s2) -->
    <div class="form-area" v-if="showForm">
      <MilitarForm
        :militar="selectedMilitar"
        :modoS2="modoS2"
        :readonlyAll="readonlyAll"
        @cancel="closeForm"
        @saved="handleSaved"
      />
    </div>

    <!-- Modal Leitura (user) -->
    <MilitarModalLeitura
      :show="showModalLeitura"
      :militarId="militarLeituraId"
      :completo="modoCompleto"
      @close="showModalLeitura = false"
    />

    <!-- Modal de Desligamento (requer PDF) -->
    <DesligarModal
      :show="showDesligarModal"
      :militar="militarParaDesligar"
      @close="showDesligarModal = false"
      @done="handleDesligarDone"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useToast, useConfirm } from '../composables/useToast'
import { useAuthStore } from '../stores/auth'
import MilitarCard from '../components/MilitarCard.vue'
import MilitarForm from '../components/MilitarForm.vue'
import MilitarModalLeitura from '../components/MilitarModalLeitura.vue'
import DesligarModal from '../components/DesligarModal.vue'
import { MilitarService } from '@/services/MilitarService'

const { error: toastError, success: toastSuccess } = useToast()
const { ask: confirmDialog } = useConfirm()

const authStore = useAuthStore()
const canEdit = computed(() => authStore.canEdit)

const postos = ["Cel", "TC", "Maj", "Cap", "1º Ten", "2º Ten", "Asp", "S Ten", "1º Sgt", "2º Sgt", "3º Sgt", "Alu", "Cb", "Sd EP", "Sd EV", "SC"]
const filters = ref({ termo: '', posto: '', subunidade: '', inativos: false, sem_foto: false, mes_aniversario: '' })
const searchResults = ref([])
const hasSearched = ref(false)
const loading = ref(false)

const showForm = ref(false)
const selectedMilitar = ref(null)
const modoS2 = ref(false)
const readonlyAll = ref(false)

const showModalLeitura = ref(false)
const militarLeituraId = ref(null)
const modoCompleto = ref(false)

const showDesligarModal = ref(false)
const militarParaDesligar = ref(null)

const handleSearch = async () => {
  loading.value = true
  hasSearched.value = true
  
  const params = {
    termo: filters.value.termo,
    posto: filters.value.posto,
    subunidade: filters.value.subunidade,
    inativos: filters.value.inativos ? 1 : 0,
    sem_foto: filters.value.sem_foto ? 1 : 0,
    mes_aniversario: filters.value.mes_aniversario
  }
  
  try {
    const json = await MilitarService.search(params)
    if (json.status === 'sucesso') searchResults.value = json.dados || []
    else searchResults.value = json || []
  } catch (e) { toastError('Erro de conexão.') }
  finally { loading.value = false }
}

// admin/sargenteacao: abre ficha para edição
const handleNovo = () => { selectedMilitar.value = null; modoS2.value = false; readonlyAll.value = false; showForm.value = true }
const handleEdit = (m) => { selectedMilitar.value = m; modoS2.value = false; readonlyAll.value = false; showForm.value = true }

// s2: abre ficha em modo inspeção
const handleInspecionar = (m) => { selectedMilitar.value = m; modoS2.value = true; readonlyAll.value = false; showForm.value = true }

// user: abre modal de leitura (ficha completa)
const abrirModalLeitura = (m) => { militarLeituraId.value = m.id; modoCompleto.value = true; showModalLeitura.value = true }

// Abrir resumo rápido (todo role)
const abrirResumo = (m) => { militarLeituraId.value = m.id; modoCompleto.value = false; showModalLeitura.value = true }

const closeForm = () => { showForm.value = false; selectedMilitar.value = null; modoS2.value = false; readonlyAll.value = false }
const handleSaved = () => { closeForm(); handleSearch() }

// Desligar: requer modal com upload PDF
const abrirDesligar = (m) => { militarParaDesligar.value = m; showDesligarModal.value = true }
const handleDesligarDone = () => { showDesligarModal.value = false; handleSearch() }

// Reativar
const handleReativar = async (m) => {
  const ok = await confirmDialog(`Deseja reintegrar o militar ${m.posto_grad} ${m.nome_guerra} ao Efetivo Pronto?`, { title: 'Confirmação' })
  if (!ok) return
  try {
    const json = await MilitarService.reativar({ id: m.id })
    if (json.status === 'sucesso') {
      toastSuccess('Militar reativado com sucesso.')
      handleSearch()
    } else toastError('Erro: ' + (json.msg || 'Erro desconhecido'))
  } catch (e) { toastError('Erro de conexão.') }
}

const handleExportar = () => {
  const q = `?tipo_busca=geral&termo=${encodeURIComponent(filters.value.termo)}&posto=${encodeURIComponent(filters.value.posto)}&su=${encodeURIComponent(filters.value.subunidade)}&inativos=${filters.value.inativos ? 1 : 0}&sem_foto=${filters.value.sem_foto ? 1 : 0}&mes_aniversario=${encodeURIComponent(filters.value.mes_aniversario)}`
  window.open('/sismil/backend/export_excel.php' + q, '_blank')
}
</script>

<style scoped>
.page-header h2 { margin: 0; color: var(--primary-blue); font-weight: 800; }
.text-muted { color: var(--text-muted); }
.mb-4 { margin-bottom: 1.5rem; }
.mb-3 { margin-bottom: 1rem; }

.search-panel { padding: 1.5rem; background: white; border-radius: var(--radius-lg); box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05); }
.search-form-container { display: flex; flex-direction: column; gap: 1.25rem; }
.search-inputs-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 1.25rem; }
.search-actions-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-top: 1px solid #f1f5f9; padding-top: 1.25rem; }
.search-checkboxes { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.4rem; }
.checkbox-wrap { display: flex; align-items: center; gap: 0.5rem; }
.checkbox-wrap label { margin-bottom: 0; font-size: 0.85rem; color: var(--text-main); cursor: pointer; font-weight: 600; }
.w-100 { width: 100%; }

@media (max-width: 992px) {
  .search-inputs-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
  .search-inputs-grid { grid-template-columns: 1fr; }
  .search-actions-bar { flex-direction: column; align-items: stretch; }
  .search-checkboxes { justify-content: flex-start; }
  .search-button-wrap button { width: 100%; }
}

.results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
.no-results { grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted); background: rgba(0,0,0,0.02); border-radius: 8px; }

.d-flex { display: flex; }
.justify-content-between { justify-content: space-between; }
.align-items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }

.badge { padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
.badge-secondary { background: #e9ecef; color: #495057; }
</style>
