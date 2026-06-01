<template>
  <div class="public-arr-page">
    <div class="glass-panel main-box">
      <div class="text-center mb-4">
        <img :src="'/sismil/uploads/brasao.png'" alt="Brasão" class="logo">
        <h3 class="text-primary mt-2">Arranchamento Semanal</h3>
        <p class="text-muted">Selecione suas refeições para os dias de expediente</p>
      </div>

      <form @submit.prevent="handleSubmit">
        <div class="form-grid mb-4">
          <div class="input-group">
            <label>Subunidade</label>
            <select v-model="form.subunidade" class="input-modern" required>
              <option value="">Selecione...</option>
              <option value="EM">Estado Maior</option>
              <option value="CCAP">CCAP</option>
              <option value="1CIA">1ª Cia Eng Cnst</option>
              <option value="2CIA">2ª Cia Eng Cnst</option>
              <option value="CIA_EQP">Cia Eqp E Mnt</option>
              <option value="PMGU">PMGU</option>
            </select>
          </div>
          <div class="input-group">
            <label>Posto / Graduação</label>
            <select v-model="form.posto_grad" class="input-modern" required>
              <option value="">Selecione...</option>
              <option v-for="p in postos" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div class="input-group">
            <label>Número (Apenas Cb/Sd)</label>
            <input type="text" v-model="form.numero" class="input-modern" placeholder="Ex: 123">
          </div>
          <div class="input-group">
            <label>Nome de Guerra</label>
            <input type="text" v-model="form.nome_guerra" class="input-modern" required>
          </div>
        </div>

        <!-- Seletor de Semana -->
        <div class="week-selector">
          <label>Semana de Referência:</label>
          <div class="week-options">
            <label class="week-radio">
              <input type="radio" v-model="weekOffset" :value="0" @change="generateDays">
              <span>Semana Atual</span>
            </label>
            <label class="week-radio">
              <input type="radio" v-model="weekOffset" :value="1" @change="generateDays">
              <span>Próxima Semana</span>
            </label>
          </div>
        </div>

        <!-- Lista de Dias -->
        <div class="days-list">
          <div v-for="day in days" :key="day.dateStr" class="day-card">
            <div class="day-header">
              <span class="day-name">{{ day.name }}</span>
              <span class="day-date">{{ day.displayDate }}</span>
            </div>
            <div class="day-meals">
              <label class="meal-checkbox">
                <input type="checkbox" v-model="day.cafe">
                <span>Café da Manhã</span>
              </label>
              <label class="meal-checkbox">
                <input type="checkbox" v-model="day.almoco">
                <span>Almoço</span>
              </label>
            </div>
          </div>
        </div>

        <div class="mt-4" style="display: flex; gap: 1rem;">
          <button type="button" class="btn-modern btn-secondary-outline w-100" @click="goToLogin">Voltar</button>
          <button type="submit" class="btn-modern btn-success w-100" :disabled="loading">
            <i class="fas fa-check"></i> {{ loading ? 'Enviando...' : 'Confirmar Refeições' }}
          </button>
        </div>
      </form>

      <div v-if="successMsg" class="success-alert mt-4">
        <i class="fas fa-check-circle"></i> {{ successMsg }}
      </div>
      <div v-if="errorMsg" class="error-alert mt-4">
        <i class="fas fa-exclamation-triangle"></i> {{ errorMsg }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const postos = ["Cel", "Ten Cel", "Maj", "Cap", "1º Ten", "2º Ten", "Asp", "Subten", "1º Sgt", "2º Sgt", "3º Sgt", "Cb", "Sd EP", "Sd EV", "SC"]

const form = ref({
  subunidade: '',
  posto_grad: '',
  numero: '',
  nome_guerra: ''
})

const weekOffset = ref(0)
const days = ref([])
const loading = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

// Gera os dias da semana (Seg a Sex) baseado no offset
const generateDays = () => {
  days.value = []
  const today = new Date()
  
  // Encontra a segunda-feira da semana atual
  const dayOfWeek = today.getDay()
  const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1) // ajusta se for domingo
  const monday = new Date(today.setDate(diff))
  
  // Adiciona o offset de semanas
  monday.setDate(monday.getDate() + (weekOffset.value * 7))
  
  const weekDayNames = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira']
  
  for (let i = 0; i < 5; i++) {
    const d = new Date(monday)
    d.setDate(monday.getDate() + i)
    
    // YYYY-MM-DD
    const isoDate = d.toISOString().split('T')[0]
    // DD/MM
    const display = d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
    
    days.value.push({
      dateStr: isoDate,
      displayDate: display,
      name: weekDayNames[i],
      cafe: false,
      almoco: false
    })
  }
}

onMounted(() => {
  generateDays()
})

const goToLogin = () => router.push({ name: 'login' })

const handleSubmit = async () => {
  successMsg.value = ''
  errorMsg.value = ''
  
  // Filtra apenas os dias que tem alguma refeição marcada
  const refeicoes = days.value.filter(d => d.cafe || d.almoco).map(d => ({
    data: d.dateStr,
    cafe: d.cafe ? 1 : 0,
    almoco: d.almoco ? 1 : 0
  }))
  
  if (refeicoes.length === 0) {
    errorMsg.value = "Selecione pelo menos uma refeição em um dos dias."
    return
  }
  
  loading.value = true
  
  const payload = {
    ...form.value,
    refeicoes
  }
  
  try {
    const res = await fetch('/sismil/backend/save_arranchamento.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    const json = await res.json()
    
    if (json.status === 'sucesso') {
      successMsg.value = "Arranchamento confirmado com sucesso!"
      // Limpa as seleções
      days.value.forEach(d => { d.cafe = false; d.almoco = false })
    } else {
      errorMsg.value = "Erro: " + json.msg
    }
  } catch (err) {
    errorMsg.value = "Erro de conexão com o servidor."
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.public-arr-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  display: flex; justify-content: center; padding: 2rem 1rem;
}
.main-box { width: 100%; max-width: 650px; padding: 2.5rem; }
.logo { width: 90px; }
.text-primary { color: var(--primary-blue); font-weight: 800; margin-bottom: 0.25rem; }
.text-center { text-align: center; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.input-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; color: #475569; }

/* Week Selector */
.week-selector { background: #f1f5f9; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; }
.week-selector label { font-weight: 700; color: var(--primary-blue); display: block; margin-bottom: 0.75rem; }
.week-options { display: flex; justify-content: center; gap: 1.5rem; }
.week-radio { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600; color: #334155; }

/* Days List */
.days-list { display: flex; flex-direction: column; gap: 0.75rem; }
.day-card {
  display: flex; align-items: center; justify-content: space-between;
  background: white; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1rem 1.25rem;
  transition: all 0.2s;
}
.day-card:hover { border-color: #94a3b8; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

.day-header { display: flex; flex-direction: column; }
.day-name { font-weight: 700; color: var(--primary-blue); font-size: 0.95rem; }
.day-date { font-size: 0.8rem; color: #64748b; font-weight: 600; }

.day-meals { display: flex; gap: 1.25rem; }
.meal-checkbox {
  display: flex; align-items: center; gap: 0.5rem; cursor: pointer;
  background: #f8fafc; padding: 0.4rem 0.8rem; border-radius: 20px; border: 1px solid #e2e8f0;
  font-size: 0.85rem; font-weight: 600; color: #475569; transition: all 0.2s;
}
.meal-checkbox:hover { background: #e2e8f0; }
.meal-checkbox input:checked + span { color: #16a34a; }

.w-100 { width: 100%; }

.success-alert { background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; font-weight: 600; text-align: center; }
.error-alert { background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; font-weight: 600; text-align: center; }

@media (max-width: 600px) {
  .form-grid { grid-template-columns: 1fr; }
  .day-card { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .day-meals { width: 100%; justify-content: space-between; }
}
</style>
