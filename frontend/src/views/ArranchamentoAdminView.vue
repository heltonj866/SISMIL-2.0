<template>
  <div class="arr-admin-page">
    <div class="page-header-banner">
      <div class="header-content">
        <div>
          <h2><i class="fas fa-clipboard-list"></i> Gestão de Arranchamento</h2>
          <p>Visualização e impressão das refeições diárias</p>
        </div>
      </div>
    </div>

    <div class="glass-panel main-panel">
      <!-- Filtro de Data -->
      <div class="filter-section">
        <div class="date-selector">
          <label>Selecione a Data:</label>
          <input type="date" v-model="selectedDate" class="input-modern" @change="fetchData">
          <button class="btn-modern btn-primary" @click="fetchData">
            <i class="fas fa-sync-alt"></i> Atualizar
          </button>
          <button class="btn-modern btn-secondary" @click="openModalExtra">
            <i class="fas fa-plus"></i> Adicionar Extra
          </button>
        </div>
        <div class="actions">
          <a :href="`/sismil/backend/print_arranchamento.php?data=${selectedDate}`" target="_blank" class="btn-modern btn-success">
            <i class="fas fa-print"></i> Imprimir Planilhas do Dia
          </a>
        </div>
      </div>

      <!-- Resumo do Dia -->
      <div v-if="loading" class="text-center text-muted mt-4">
        <i class="fas fa-spinner fa-spin fa-2x"></i>
        <p class="mt-2">Carregando dados...</p>
      </div>
      <div v-else class="summary-grid mt-4">
        <div class="stat-card card-amber">
          <div class="stat-icon"><i class="fas fa-coffee"></i></div>
          <div class="stat-body">
            <div class="stat-number">{{ totais.cafe }}</div>
            <div class="stat-label">Café da Manhã</div>
          </div>
        </div>
        <div class="stat-card card-green">
          <div class="stat-icon"><i class="fas fa-utensils"></i></div>
          <div class="stat-body">
            <div class="stat-number">{{ totais.almoco }}</div>
            <div class="stat-label">Almoço</div>
          </div>
        </div>
        <div class="stat-card card-dark">
          <div class="stat-icon"><i class="fas fa-moon"></i></div>
          <div class="stat-body">
            <div class="stat-number">{{ totais.jantar || 0 }}</div>
            <div class="stat-label">Jantar</div>
          </div>
        </div>
        <div class="stat-card card-blue">
          <div class="stat-icon"><i class="fas fa-users"></i></div>
          <div class="stat-body">
            <div class="stat-number">{{ registros.length }}</div>
            <div class="stat-label">Militares Arranchados</div>
          </div>
        </div>
      </div>
      
      <!-- Lista de Militares Arranchados -->
      <div v-if="!loading" class="list-section mt-5">
        <h5>Registros de Refeições: {{ formattedDate }}</h5>
        <div class="table-responsive">
          <table class="table-modern mt-3">
            <thead>
              <tr>
                <th>Subunidade</th>
                <th>Posto/Grad</th>
                <th>Número</th>
                <th>Nome de Guerra</th>
                <th class="text-center">Café</th>
                <th class="text-center">Almoço</th>
                <th class="text-center">Jantar</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in registros" :key="r.id">
                <td><span class="badge-sub">{{ r.subunidade }}</span></td>
                <td>{{ r.posto_grad }}</td>
                <td>{{ r.numero || '---' }}</td>
                <td class="fw-bold">{{ r.nome_guerra }}</td>
                <td class="text-center" @click="toggleRefeicao(r, 'cafe')" style="cursor: pointer;">
                  <i v-if="r.cafe == 1" class="fas fa-check-circle text-success"></i>
                  <i v-else class="fas fa-minus text-muted"></i>
                </td>
                <td class="text-center" @click="toggleRefeicao(r, 'almoco')" style="cursor: pointer;">
                  <i v-if="r.almoco == 1" class="fas fa-check-circle text-success"></i>
                  <i v-else class="fas fa-minus text-muted"></i>
                </td>
                <td class="text-center" @click="toggleRefeicao(r, 'jantar')" style="cursor: pointer;">
                  <i v-if="r.jantar == 1" class="fas fa-check-circle text-success"></i>
                  <i v-else class="fas fa-minus text-muted"></i>
                </td>
              </tr>
              <tr v-if="registros.length === 0">
                <td colspan="6" class="text-center text-muted p-4">Nenhum militar arranchado para este dia.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Adicionar Extra -->
    <div v-if="showModalExtra" class="modal-overlay" @click.self="closeModalExtra">
      <div class="modal-content glass-panel" style="max-width: 500px;">
        <div class="modal-header">
          <h5><i class="fas fa-plus-circle"></i> Adicionar Arranchado Extra</h5>
          <button class="btn-close" @click="closeModalExtra">&times;</button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="submitExtra">
            <div class="mb-3">
              <label>Nome / Identificação</label>
              <input type="text" v-model="formExtra.nome_guerra" class="input-modern w-100" placeholder="Ex: Guarnição de Serviço" required>
            </div>
            <div class="mb-3">
              <label>Posto/Graduação Contábil</label>
              <select v-model="formExtra.posto_grad" class="input-modern w-100" required>
                <option value="Cb">Cabo/Soldado (Contabiliza com Cb/Sd)</option>
                <option value="3º Sgt">Sargento (Contabiliza com Of/Sgt)</option>
                <option value="Civil">Civil / Outros (Não oficial)</option>
              </select>
            </div>
            <div class="mb-3">
              <label>Refeições:</label>
              <div class="d-flex" style="gap: 15px; margin-top: 10px;">
                <label><input type="checkbox" v-model="formExtra.cafe"> Café</label>
                <label><input type="checkbox" v-model="formExtra.almoco"> Almoço</label>
                <label><input type="checkbox" v-model="formExtra.jantar"> Jantar</label>
              </div>
            </div>
            <div class="mt-4 text-end">
              <button type="button" class="btn-modern btn-secondary-outline me-2" @click="closeModalExtra">Cancelar</button>
              <button type="submit" class="btn-modern btn-primary" :disabled="savingExtra">
                {{ savingExtra ? 'Salvando...' : 'Salvar Extra' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from '../composables/useToast'
import { ArranchamentoService } from '@/services/ArranchamentoService'

const { error: toastError } = useToast()

const today = new Date().toISOString().split('T')[0]
const selectedDate = ref(today)
const loading = ref(false)

const totais = ref({ cafe: 0, almoco: 0, jantar: 0 })
const registros = ref([])

const showModalExtra = ref(false)
const savingExtra = ref(false)
const formExtra = ref({ nome_guerra: '', posto_grad: 'Cb', cafe: false, almoco: false, jantar: false })

const authStore = useAuthStore() // If needed for subunidade, but we can pass 'EXTRA'


const formattedDate = computed(() => {
  if (!selectedDate.value) return ''
  const [y, m, d] = selectedDate.value.split('-')
  return `${d}/${m}/${y}`
})

const fetchData = async () => {
  if (!selectedDate.value) return
  loading.value = true
  try {
    const json = await ArranchamentoService.getByData(selectedDate.value)
    if (json.status === 'sucesso' || json.registros) {
      totais.value = json.totais || { cafe: 0, almoco: 0, jantar: 0 }
      registros.value = json.registros || []
    } else {
      toastError("Erro: " + (json.msg || 'Erro desconhecido'))
    }
  } catch (err) {
    toastError("Erro de conexão com o servidor.")
  } finally {
    loading.value = false
  }
}

const toggleRefeicao = async (registro, tipo) => {
  try {
    const newValue = registro[tipo] == 1 ? 0 : 1;
    // Update local immediately for UX
    registro[tipo] = newValue;
    
    // Call backend
    const payload = {
      id: registro.id,
      data: selectedDate.value,
      subunidade: registro.subunidade,
      posto_grad: registro.posto_grad,
      nome_guerra: registro.nome_guerra,
      cafe: registro.cafe,
      almoco: registro.almoco,
      jantar: registro.jantar
    };
    
    await ArranchamentoService.saveExtra(payload);
    useToast().success("Refeição atualizada.");
    fetchData(); // Refresh totals
  } catch(e) {
    useToast().error("Erro ao atualizar refeição.");
    fetchData(); // Revert UX change
  }
}

const openModalExtra = () => {
  formExtra.value = { nome_guerra: '', posto_grad: 'Cb', cafe: false, almoco: false, jantar: false }
  showModalExtra.value = true
}

const closeModalExtra = () => {
  showModalExtra.value = false
}

const submitExtra = async () => {
  savingExtra.value = true;
  const payload = {
    data: selectedDate.value,
    subunidade: 'EXTRA',
    posto_grad: formExtra.value.posto_grad,
    nome_guerra: formExtra.value.nome_guerra,
    cafe: formExtra.value.cafe ? 1 : 0,
    almoco: formExtra.value.almoco ? 1 : 0,
    jantar: formExtra.value.jantar ? 1 : 0
  }
  
  try {
    await ArranchamentoService.saveExtra(payload)
    useToast().success("Arranchado extra adicionado!")
    closeModalExtra()
    fetchData()
  } catch(e) {
    useToast().error("Erro ao salvar extra.")
  } finally {
    savingExtra.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<style scoped>
.arr-admin-page { padding-bottom: 2rem; }

.page-header-banner {
  background: linear-gradient(120deg, #92400e 0%, #b45309 100%);
  color: white; border-radius: 16px; padding: 1.75rem 2rem;
  margin-bottom: 1.5rem; box-shadow: 0 6px 24px rgba(0,0,0,.2);
}
.header-content h2 { margin: 0 0 .25rem; font-weight: 800; font-size: 1.4rem; }
.header-content h2 i { margin-right: .5rem; opacity: .8; }
.header-content p { margin: 0; opacity: .8; font-size: .88rem; }

.main-panel { padding: 1.5rem 2rem; }

.filter-section {
  display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem;
  padding-bottom: 1.5rem; border-bottom: 2px solid #f1f5f9;
}
.date-selector { display: flex; align-items: flex-end; gap: 0.75rem; }
.date-selector label { display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.4rem; }

.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; }
.stat-card {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.5rem; border-radius: 12px; color: white;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.card-amber { background: linear-gradient(135deg, #d97706, #f59e0b); }
.card-green { background: linear-gradient(135deg, #16a34a, #22c55e); }
.card-blue  { background: linear-gradient(135deg, #2563eb, #3b82f6); }
.stat-icon { font-size: 2rem; opacity: 0.8; }
.stat-number { font-size: 1.8rem; font-weight: 800; line-height: 1; }
.stat-label { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-top: 0.25rem; opacity: 0.9; }

.list-section h5 { color: var(--primary-blue); font-weight: 700; border-left: 4px solid var(--primary-blue); padding-left: 0.5rem; }
.badge-sub { background: #e2e8f0; color: #334155; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem; }
.text-success { color: #16a34a; }

.table-modern { width: 100%; border-collapse: collapse; }
.table-modern th { background: #f8fafc; padding: 0.75rem 1rem; text-align: left; font-size: 0.8rem; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
.table-modern td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; }
.table-modern tr:hover td { background: #f8fafc; }

/* Modal Styles */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1050; display: flex; align-items: center; justify-content: center; }
.modal-content { background: white; border-radius: 12px; padding: 1.5rem; width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; }
.modal-header h5 { margin: 0; color: var(--primary-blue); font-weight: 700; }
.btn-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; }
.btn-close:hover { color: #dc3545; }

@media (max-width: 700px) {
  .summary-grid { grid-template-columns: 1fr; }
  .filter-section { flex-direction: column; align-items: stretch; }
}
</style>
