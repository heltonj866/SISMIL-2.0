<template>
  <div class="dashboard-layout">
    <header class="top-nav">
      <div class="nav-brand">
        <img :src="'/sismil/uploads/brasao.png'" alt="Logo" class="nav-logo">
        <div class="brand-text">
          <span class="title">2º BECnst</span>
          <span class="subtitle">| CARÔMETRO</span>
        </div>
      </div>
      
      <div class="nav-actions">
        <div class="user-info">
          <span class="logged-as">Logado como</span>
          <strong class="role-name">{{ authStore.userRole?.toUpperCase() }}</strong>
        </div>
        <button @click="handleLogout" class="btn-modern btn-logout">Sair</button>
      </div>
    </header>

    <main class="dashboard-content">
      <div class="stats-row">
        <div class="stat-card bg-primary-grad">
          <div class="stat-info">
            <h6>Efetivo Cadastrado</h6>
            <h2>{{ stats.militares }}</h2>
          </div>
        </div>
        <div class="stat-card bg-secondary-grad">
          <div class="stat-info">
            <h6>Veículos Cadastrados</h6>
            <h2>{{ stats.veiculos }}</h2>
          </div>
        </div>
        <div class="stat-card bg-warning-grad">
          <div class="stat-info">
            <h6 class="text-danger">Aguardando Homologação</h6>
            <h2 class="text-danger">{{ stats.pendentes }}</h2>
          </div>
        </div>
      </div>

      <div class="glass-panel main-panel search-panel mb-4">
        <div class="tabs">
          <button class="tab-btn active">Busca Geral</button>
          <!-- Additional tabs can be added here if needed -->
        </div>
        
        <form @submit.prevent="handleSearch" class="search-form">
          <div class="form-grid">
            <div class="input-group">
              <label>Nome / Guerra</label>
              <input type="text" v-model="filters.termo" class="input-modern" placeholder="Digite...">
            </div>
            
            <div class="input-group">
              <label>Posto/Grad</label>
              <select v-model="filters.posto" class="input-modern">
                <option value="">Todos</option>
                <option v-for="p in postos" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            
            <div class="input-group">
              <label>Exibir Desligados</label>
              <input type="checkbox" v-model="filters.inativos">
            </div>
            
            <div class="input-group d-flex-end">
              <button type="submit" class="btn-modern btn-primary-modern w-100" :disabled="loading">
                <span v-if="loading">Buscando...</span>
                <span v-else>Buscar</span>
              </button>
            </div>
          </div>
        </form>
      </div>

      <div class="results-header" v-if="hasSearched && !showForm">
        <h5>Resultados <span class="badge badge-secondary">{{ searchResults.length }}</span></h5>
        <div class="actions">
           <button class="btn-modern btn-success btn-sm" v-if="['admin', 'sargenteacao'].includes(authStore.userRole?.toLowerCase())" @click="handleNovo">
             + Novo Cadastro
           </button>
        </div>
      </div>

      <div class="results-grid mt-3" v-if="hasSearched && !showForm">
        <MilitarCard 
          v-for="militar in searchResults" 
          :key="militar.id" 
          :militar="militar"
          @editar="handleEdit"
          @desligar="handleDesligar"
          @reativar="handleReativar"
          @inspecionar="handleInspecionar"
          @ver="handleVer"
        />
        <div v-if="searchResults.length === 0" class="no-results">
          Nenhum registro encontrado.
        </div>
      </div>
      
      <div class="form-area mt-4" v-if="showForm">
        <MilitarForm :militar="selectedMilitar" @cancel="closeForm" @saved="handleSaved" />
      </div>

      <VeiculoModal 
        :show="showModalS2" 
        :militar="selectedMilitar" 
        @close="showModalS2 = false; selectedMilitar = null"
        @updated="handleSaved"
      />
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from '../composables/useToast'
const { warning: toastWarning } = useToast()
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import MilitarCard from '../components/MilitarCard.vue'
import MilitarForm from '../components/MilitarForm.vue'
import VeiculoModal from '../components/VeiculoModal.vue'

const router = useRouter()
const authStore = useAuthStore()

const postos = ["Cel", "Ten Cel", "Maj", "Cap", "1º Ten", "2º Ten", "Asp", "Subten", "1º Sgt", "2º Sgt", "3º Sgt", "Cb", "Sd EP", "Sd EV", "SC"]

const stats = ref({ militares: 0, veiculos: 0, pendentes: 0 })
const searchResults = ref([])
const hasSearched = ref(false)
const loading = ref(false)

const showForm = ref(false)
const showModalS2 = ref(false)
const selectedMilitar = ref(null)

const filters = ref({
  termo: '',
  posto: '',
  qmg: '',
  inativos: false
})

const fetchStats = async () => {
  try {
    const res = await fetch('/sismil/backend/dashboard_stats.php')
    const json = await res.json()
    if (json.status === 'sucesso') {
      stats.value.militares = json.militares
      stats.value.veiculos = json.veiculos
      stats.value.pendentes = json.pendentes
    }
  } catch (err) { console.error(err) }
}

onMounted(() => {
  fetchStats()
})

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}

const handleSearch = async () => {
  loading.value = true
  hasSearched.value = true
  try {
    const inativos = filters.value.inativos ? 1 : 0
    const url = `/sismil/backend/search.php?tipo_busca=geral&termo=${encodeURIComponent(filters.value.termo)}&posto=${encodeURIComponent(filters.value.posto)}&qmg=&inativos=${inativos}`
    const res = await fetch(url)
    const json = await res.json()
    if (json.status === 'sucesso') {
      searchResults.value = json.dados || []
    } else {
      searchResults.value = []
    }
  } catch (err) {
    console.error(err)
    searchResults.value = []
  } finally {
    loading.value = false
  }
}

// Action Handlers
const handleNovo = () => {
  selectedMilitar.value = null
  showForm.value = true
}

const handleEdit = (m) => {
  selectedMilitar.value = m
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  selectedMilitar.value = null
}

const handleSaved = () => {
  closeForm()
  fetchStats()
  if (hasSearched.value) {
    handleSearch()
  }
}

const handleDesligar = (m) => toastWarning('Função de desligar em desenvolvimento.')
const handleReativar = (m) => toastWarning('Função de reativar em desenvolvimento.')

const handleInspecionar = (m) => {
  selectedMilitar.value = m
  showModalS2.value = true
}

const handleVer = (m) => {
  // Can just open edit mode for viewing, and handle readonly inside MilitarForm
  // For now, let's just open form
  selectedMilitar.value = m
  showForm.value = true
}
</script>

<style scoped>
.dashboard-layout { min-height: 100vh; background-color: var(--bg-color); }
.top-nav { background-color: var(--primary-blue); color: white; padding: 0.75rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-md); }
.nav-brand { display: flex; align-items: center; gap: 1rem; }
.nav-logo { height: 45px; }
.brand-text { display: flex; align-items: baseline; gap: 0.5rem; }
.brand-text .title { font-size: 1.25rem; font-weight: 700; }
.brand-text .subtitle { font-size: 0.85rem; opacity: 0.75; }
.nav-actions { display: flex; align-items: center; gap: 1.5rem; }
.user-info { display: flex; flex-direction: column; text-align: right; line-height: 1.2; }
.logged-as { font-size: 0.75rem; opacity: 0.75; }
.role-name { font-size: 0.9rem; }
.btn-logout { background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255, 255, 255, 0.2); padding: 0.4rem 1rem; font-size: 0.85rem; }
.btn-logout:hover { background: rgba(255, 255, 255, 0.2); }
.dashboard-content { padding: 2rem; max-width: 1200px; margin: 0 auto; }
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { padding: 1.5rem; border-radius: var(--radius-lg); color: white; box-shadow: var(--shadow-sm); transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-4px); }
.bg-primary-grad { background: linear-gradient(135deg, var(--primary-blue) 0%, #1A5A96 100%); }
.bg-secondary-grad { background: linear-gradient(135deg, #495057 0%, #6c757d 100%); }
.bg-warning-grad { background: linear-gradient(135deg, #ffc107 0%, #ffdf7e 100%); color: #212529 !important; }
.stat-info h6 { font-size: 0.8rem; text-transform: uppercase; font-weight: 700; opacity: 0.8; margin-bottom: 0.25rem; }
.stat-info h2 { font-size: 2.5rem; font-weight: 800; margin:0; }
.text-danger { color: #dc3545; }

.search-panel { padding: 0; overflow: hidden; }
.tabs { display: flex; border-bottom: 1px solid #dee2e6; background: #fff; }
.tab-btn { background: transparent; border: none; padding: 1rem 1.5rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; }
.tab-btn.active { color: var(--primary-blue); border-bottom-color: var(--primary-blue); }
.search-form { padding: 1.5rem; background: #fafbfc; }
.form-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 1rem; align-items: end; }
.input-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.4rem; text-transform: uppercase; }
.d-flex-end { display: flex; align-items: flex-end; }
.w-100 { width: 100%; }

.results-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #dee2e6; padding-bottom: 0.5rem; }
.badge-secondary { background: #6c757d; color: white; padding: 0.2rem 0.5rem; border-radius: 12px; font-size: 0.8rem; }
.btn-success { background: var(--success); color: white; border:none; }

.results-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
.no-results { grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted); font-size: 1.1rem; }
.mb-4 { margin-bottom: 1.5rem; }
.mt-3 { margin-top: 1rem; }

@media (max-width: 900px) {
  .stats-row { grid-template-columns: 1fr; }
  .form-grid { grid-template-columns: 1fr; }
  .results-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
