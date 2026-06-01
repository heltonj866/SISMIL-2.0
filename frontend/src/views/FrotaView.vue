<template>
  <div class="frota-view">
    <header class="page-header d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2>Frota e Condutores (S2)</h2>
        <p class="text-muted">Homologação de veículos, inspeção e emissão de selos</p>
      </div>
      <a href="/sismil/backend/relatorio_s2.php" target="_blank" class="btn-modern btn-secondary-outline">
        <i class="fas fa-file-invoice"></i> Relatório Geral S2
      </a>
    </header>

    <!-- Busca por CNH (exclusivo desta tela) -->
    <div class="search-panel glass-panel mb-4">
      <form @submit.prevent="handleSearch" class="search-form">
        <div class="input-group">
          <label>Filtro por Categoria CNH</label>
          <div class="radio-group">
            <label v-for="opt in filtroOpts" :key="opt.value" class="radio-opt" :class="{ active: filtro === opt.value }">
              <input type="radio" v-model="filtro" :value="opt.value" hidden>
              {{ opt.label }}
            </label>
          </div>
        </div>
        <div class="input-group" style="align-self: flex-end;">
          <button type="submit" class="btn-modern btn-primary" :disabled="loading">
            <i class="fas fa-filter"></i> {{ loading ? 'Buscando...' : 'Listar Condutores' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Resultados -->
    <div class="results-area" v-if="hasSearched">
      <div class="results-header mb-3">
        <h5>
          Condutores Encontrados
          <span class="badge badge-secondary">{{ resultados.length }}</span>
        </h5>
      </div>
      <div class="results-grid">
        <MilitarCard
          v-for="m in resultados"
          :key="m.id"
          :militar="m"
          @inspecionar="abrirInspecao"
          @editar="abrirInspecao"
          @resumo="abrirResumo"
          @verFicha="abrirResumo"
          @desligar="() => {}"
          @reativar="() => {}"
        />
        <div v-if="resultados.length === 0" class="no-results">
          <i class="fas fa-search fa-2x mb-2"></i><br>
          Nenhum condutor encontrado para esta categoria.
        </div>
      </div>
    </div>

    <!-- Formulário de Inspeção S2 (ficha readonly + veículos) -->
    <div class="form-area mt-4" v-if="showInspecao">
      <MilitarForm
        :militar="militarSelecionado"
        :modoS2="true"
        @cancel="showInspecao = false; militarSelecionado = null"
        @saved="() => {}"
      />
    </div>

    <!-- Modal de Resumo -->
    <MilitarModalLeitura
      :show="showResumo"
      :militarId="resumoId"
      @close="showResumo = false"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from '../composables/useToast'
const { error: toastError } = useToast()
import MilitarCard from '../components/MilitarCard.vue'
import MilitarForm from '../components/MilitarForm.vue'
import MilitarModalLeitura from '../components/MilitarModalLeitura.vue'

const route = useRoute()

const filtroOpts = [
  { value: 'TODAS', label: 'Todas' },
  { value: 'A', label: 'A (Moto)' },
  { value: 'B', label: 'B (Carro)' },
  { value: 'AB', label: 'AB' },
  { value: 'C', label: 'C' },
  { value: 'D', label: 'D' },
  { value: 'E', label: 'E' },
  { value: 'PRO', label: 'Profissional (C/D/E)' },
  { value: 'PENDENTES', label: 'Pendentes' }
]

const filtro = ref(route.query.filtro || 'TODAS')
const resultados = ref([])
const hasSearched = ref(false)
const loading = ref(false)

const showInspecao = ref(false)
const militarSelecionado = ref(null)
const showResumo = ref(false)
const resumoId = ref(null)

const handleSearch = async () => {
  loading.value = true
  hasSearched.value = true
  showInspecao.value = false
  try {
    const res = await fetch(`/sismil/backend/search.php?tipo_busca=cnh&filtro_cnh=${filtro.value}`)
    const json = await res.json()
    if (json.status === 'sucesso') resultados.value = json.dados
  } catch (e) {
    toastError('Erro de conexão ao buscar condutores.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (route.query.filtro) {
    handleSearch()
  }
})

const abrirInspecao = (m) => {
  militarSelecionado.value = m
  showInspecao.value = true
  // Scroll para o formulário
  setTimeout(() => {
    document.querySelector('.form-area')?.scrollIntoView({ behavior: 'smooth' })
  }, 100)
}

const abrirResumo = (m) => {
  resumoId.value = m.id
  showResumo.value = true
}
</script>

<style scoped>
.page-header h2 { margin: 0; color: var(--primary-blue); font-weight: 800; }
.text-muted { color: var(--text-muted); }
.mb-4 { margin-bottom: 1.5rem; }
.mb-3 { margin-bottom: 1rem; }
.mt-4 { margin-top: 1.5rem; }

.search-panel { padding: 1.5rem; background: white; border-radius: var(--radius-lg); }
.search-form { display: grid; grid-template-columns: 1fr auto; gap: 1.5rem; align-items: flex-end; }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.75rem; }
.radio-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.radio-opt {
  padding: 0.4rem 0.9rem; border: 1px solid #dee2e6; border-radius: 20px;
  font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
  color: var(--text-muted); background: #f8f9fa;
}
.radio-opt.active { background: var(--primary-blue); color: white; border-color: var(--primary-blue); }
.radio-opt:hover:not(.active) { background: var(--secondary-blue); color: var(--primary-blue); border-color: var(--primary-blue); }

.results-header { display: flex; align-items: center; gap: 1rem; }
.results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem; }
.no-results { grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--text-muted); background: rgba(0,0,0,0.02); border-radius: 8px; }

.d-flex { display: flex; }
.justify-content-between { justify-content: space-between; }
.align-items-center { align-items: center; }

.badge { padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
.badge-secondary { background: #e9ecef; color: #495057; }
.mb-2 { margin-bottom: 0.5rem; }
</style>
