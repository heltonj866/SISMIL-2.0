<template>
  <div class="public-container">
    <div class="top-bar">
      <div class="container">
        <button @click="$router.push('/login')" class="btn-back">
          ← Voltar ao Login
        </button>
        <div class="brand">
          <img :src="'/sismil/uploads/brasao.png'" alt="Brasão" class="brasao" @error="e => e.target.style.display='none'">
          <div>
            <div class="title">2º BECnst</div>
            <div class="subtitle">Consulta Pública</div>
          </div>
        </div>
      </div>
    </div>

    <div class="container main-content">
      <div class="glass-panel search-card">
        <h5 class="search-title">Buscar Militar ou Veículo</h5>
        <form @submit.prevent="handleSearch" class="search-form">
          <input
            type="text"
            v-model="termo"
            class="input-modern input-lg"
            placeholder="Nome de guerra, posto, placa ou modelo do veículo..."
            required
          />
          <button type="submit" class="btn-buscar" :disabled="loading">
            <i class="fas fa-search"></i>
            {{ loading ? 'Buscando...' : 'Buscar' }}
          </button>
        </form>
      </div>

      <div v-if="error" class="error-alert">
        <i class="fas fa-exclamation-circle"></i> {{ error }}
      </div>

      <div v-if="hasSearched && !loading && resultados.length === 0" class="empty-state">
        <i class="fas fa-search fa-3x mb-3 opacity-25"></i><br>
        Nenhum militar encontrado para "<strong>{{ termoUsado }}</strong>".
      </div>

      <div class="results-info" v-if="resultados.length > 0">
        <span>{{ resultados.length }} resultado(s) para "<strong>{{ termoUsado }}</strong>"</span>
      </div>

      <div class="results-grid" v-if="resultados.length > 0">
        <div class="result-card glass-panel" v-for="d in resultados" :key="d.id">
          <div class="card-content">
            <!-- Foto -->
            <div class="foto-area">
              <img :src="getFoto(d.foto_path)" class="militar-foto" @error="handleImgError">
              <div class="status-badge" v-if="d.status_ativo == 0">DESLIGADO</div>
            </div>

            <!-- Dados do Militar -->
            <div class="militar-info">
              <h5 class="nome-guerra">{{ d.posto_grad }} {{ d.nome_guerra }}</h5>
              <div class="nome-completo">{{ d.nome_completo || '---' }}</div>

              <div class="badges">
                <span class="badge badge-primary">{{ d.subunidade || 'OM' }}</span>
                <span class="badge badge-dark" v-if="d.secao || d.pelotao">{{ d.secao || d.pelotao }}</span>
                <span class="badge badge-cnh" v-if="d.cat_cnh">
                  <i class="fas fa-id-card"></i> CNH {{ d.cat_cnh }}
                </span>
              </div>

              <ul class="info-list">
                <li><i class="fas fa-phone"></i> {{ d.celular_princ || 'Não informado' }}</li>
                <li><i class="fas fa-birthday-cake"></i> {{ formatData(d.dt_nascimento) }}</li>
                <li><i class="fas fa-calendar-check"></i> Praça: {{ formatData(d.dt_praca) }}</li>
              </ul>
            </div>

            <!-- Veículo -->
            <div class="veiculo-area">
              <div v-if="!d.veiculo" class="no-vehicle">
                <i class="fas fa-car-crash"></i><br>SEM VEÍCULO
              </div>
              <div v-else class="vehicle-card" :class="d.veiculo.homologado == 1 ? 'v-ok' : 'v-pending'">
                <div class="v-modelo">{{ d.veiculo.modelo }}</div>
                <div class="v-cor text-muted">{{ d.veiculo.cor }}</div>
                <div class="v-placa">{{ d.veiculo.placa }}</div>
                <div class="v-status">
                  <i :class="d.veiculo.homologado == 1 ? 'fas fa-check-circle' : 'fas fa-clock'"></i>
                  {{ d.veiculo.homologado == 1 ? 'LIBERADO' : 'PENDENTE' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const termo = ref('')
const termoUsado = ref('')
const resultados = ref([])
const loading = ref(false)
const error = ref('')
const hasSearched = ref(false)

const formatData = (dataUSA) => {
  if (!dataUSA || dataUSA === '0000-00-00' || dataUSA === '') return '---'
  const partes = dataUSA.split('-')
  if (partes.length !== 3) return dataUSA
  return `${partes[2]}/${partes[1]}/${partes[0]}`
}

const getFoto = (path) => path ? `/sismil/uploads/${path}` : '/sismil/assets/sem_foto.png'
const handleImgError = (e) => { e.target.src = '/sismil/assets/sem_foto.png' }

const handleSearch = async () => {
  if (!termo.value.trim()) return
  loading.value = true
  error.value = ''
  hasSearched.value = true
  termoUsado.value = termo.value.trim()

  try {
    const req = await fetch(`/sismil/backend/api/militar/search?termo=${encodeURIComponent(termoUsado.value)}&publico=1`)
    const json = await req.json()

    if (json.status === 'erro' && json.msg && !json.dados) {
      error.value = json.msg
      resultados.value = []
    } else {
      resultados.value = json.dados || []
    }
  } catch (err) {
    error.value = 'Erro de conexão com o servidor.'
    resultados.value = []
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.public-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #eaf0fb 0%, #dde8f5 100%);
}

.top-bar {
  background: white;
  padding: 1rem 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  margin-bottom: 2rem;
}

.top-bar .container {
  max-width: 960px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 0 1.5rem;
}

.brand { display: flex; align-items: center; gap: 0.75rem; }
.brasao { height: 42px; }
.brand .title { font-weight: 800; color: var(--primary-blue); font-size: 1.1rem; line-height: 1; }
.brand .subtitle { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; letter-spacing: 1px; }

.btn-back {
  background: transparent; border: 1px solid #adb5bd; color: #6c757d;
  padding: 0.45rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer;
  font-size: 0.85rem; transition: all 0.2s; white-space: nowrap;
}
.btn-back:hover { background: #6c757d; color: white; }

.main-content { max-width: 960px; margin: 0 auto; padding: 0 1.5rem 3rem; }

.search-card { padding: 2rem; margin-bottom: 1.5rem; }
.search-title { color: var(--primary-blue); font-weight: 700; margin-bottom: 1rem; font-size: 1.1rem; }
.search-form { display: flex; gap: 1rem; }
.input-lg { font-size: 1rem; padding: 0.8rem 1.25rem; flex: 1; }
.btn-buscar {
  background: var(--primary-blue); color: white;
  border: none; padding: 0.8rem 2rem; border-radius: 10px;
  font-weight: 700; font-size: 1rem; cursor: pointer; white-space: nowrap;
  transition: all 0.2s; display: flex; align-items: center; gap: 0.5rem;
}
.btn-buscar:hover:not(:disabled) { background: #063a6e; transform: translateY(-1px); }
.btn-buscar:disabled { opacity: 0.6; cursor: not-allowed; }

.error-alert {
  background: #f8d7da; color: #842029; padding: 1rem; border-radius: 8px;
  margin-bottom: 1rem; text-align: center; border: 1px solid #f5c2c7;
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.empty-state {
  text-align: center; padding: 3rem; color: var(--text-muted); font-size: 1.1rem; background: white; border-radius: 12px;
}
.mb-3 { margin-bottom: 1rem; }
.opacity-25 { opacity: 0.25; }

.results-info {
  font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem; padding: 0 0.25rem;
}
.results-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }

.result-card { padding: 1.5rem; transition: transform 0.2s, box-shadow 0.2s; }
.result-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }

.card-content { display: flex; gap: 1.5rem; align-items: flex-start; }

.foto-area { position: relative; flex-shrink: 0; }
.militar-foto { width: 90px; height: 115px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.status-badge {
  position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
  background: #dc3545; color: white; font-size: 0.6rem; font-weight: 700;
  padding: 0.1rem 0.4rem; border-radius: 4px; white-space: nowrap;
}

.militar-info { flex: 1; min-width: 0; }
.nome-guerra { color: var(--primary-blue); font-weight: 800; text-transform: uppercase; margin: 0 0 0.2rem; font-size: 1rem; }
.nome-completo { font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.6rem; }
.badges { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.6rem; }
.badge { font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600; }
.badge-primary { background: var(--secondary-blue); color: var(--primary-blue); }
.badge-dark { background: #212529; color: white; }
.badge-cnh { background: #d1e7dd; color: #0f5132; }

.info-list { list-style: none; padding: 0; margin: 0; font-size: 0.82rem; color: var(--text-main); }
.info-list li { margin-bottom: 0.2rem; display: flex; align-items: center; gap: 0.4rem; }
.info-list i { width: 14px; color: var(--text-muted); }

.veiculo-area { flex-shrink: 0; min-width: 160px; }
.no-vehicle {
  background: #f1f3f5; color: var(--text-muted); font-size: 0.75rem;
  text-align: center; padding: 1rem; border-radius: 8px; font-weight: 600;
}
.no-vehicle i { display: block; font-size: 1.5rem; margin-bottom: 0.25rem; opacity: 0.4; }

.vehicle-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 0.75rem; text-align: center; }
.vehicle-card.v-ok { border-color: #badbcc; background: #f0faf4; }
.vehicle-card.v-pending { border-color: #ffe69c; background: #fffbea; }
.v-modelo { font-weight: 700; font-size: 0.82rem; margin-bottom: 0.2rem; text-transform: uppercase; }
.v-cor { font-size: 0.75rem; margin-bottom: 0.4rem; }
.v-placa {
  display: inline-block; background: white; border: 2px solid #333;
  padding: 0.1rem 0.5rem; border-radius: 4px; font-family: monospace;
  font-weight: 800; font-size: 0.9rem; margin-bottom: 0.5rem; text-transform: uppercase;
}
.v-status { font-size: 0.72rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 0.3rem; }
.v-ok .v-status { color: #0f5132; }
.v-pending .v-status { color: #664d03; }

@media (max-width: 600px) {
  .search-form { flex-direction: column; }
  .card-content { flex-direction: column; }
  .foto-area { align-self: center; }
  .veiculo-area { width: 100%; }
  
  .top-bar .container {
    flex-direction: column-reverse;
    align-items: stretch;
    gap: 0.75rem;
  }
  .brand {
    justify-content: center;
  }
  .btn-back {
    text-align: center;
  }
}
</style>
