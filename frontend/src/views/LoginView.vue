<template>
  <div class="login-container">
    <div class="glass-panel login-card">
      <div class="logo-area">
        <img :src="'/sismil/uploads/brasao.png'" alt="Brasão 2º BEC" class="logo">
        <h4 class="title">2º Batalhão de Engenharia</h4>
        <h6 class="subtitle">"Batalhão Heróis do Jenipapo"</h6>
      </div>

      <form @submit.prevent="handleLogin" class="login-form">
        <div class="input-group">
          <label>CPF</label>
          <input 
            type="text" 
            v-model="cpf" 
            class="input-modern" 
            placeholder="000.000.000-00" 
            required 
            @input="mascaraCPF"
          />
        </div>
        
        <div class="input-group">
          <label>Senha</label>
          <input 
            type="password" 
            v-model="senha" 
            class="input-modern" 
            placeholder="••••••••" 
            required
          />
        </div>

        <p v-if="errorMessage" class="error-msg">{{ errorMessage }}</p>

        <button type="submit" class="btn-modern btn-primary-modern w-100" :disabled="loading">
          {{ loading ? 'Verificando...' : 'Entrar no Sistema' }}
        </button>

        <button type="button" @click="goToPublicSearch" class="btn-modern btn-secondary w-100 mt-3">
          <i class="fas fa-search"></i> Consulta Pública
        </button>

        <button type="button" @click="goToArranchamento" class="btn-modern btn-secondary w-100" style="margin-top: 0.75rem; border-color: #f59e0b; color: #d97706;">
          <i class="fas fa-utensils"></i> Arranchamento Semanal
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const cpf = ref('')
const senha = ref('')
const errorMessage = ref('')
const loading = ref(false)

const router = useRouter()
const authStore = useAuthStore()

const mascaraCPF = (e) => {
  let v = e.target.value.replace(/\D/g, "").substring(0, 11)
  v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4")
  cpf.value = v
}

const handleLogin = async () => {
  loading.value = true
  errorMessage.value = ''
  
  try {
    const res = await fetch('/sismil/backend/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identidade: cpf.value, senha: senha.value })
    })
    
    const data = await res.json()
    if (data.status === 'sucesso') {
      authStore.setSession(data.role, cpf.value)
      router.push({ name: 'dashboard' })
    } else {
      errorMessage.value = data.msg || 'Erro ao realizar login.'
    }
  } catch (err) {
    errorMessage.value = 'Erro de conexão com o servidor.'
  } finally {
    loading.value = false
  }
}

const goToPublicSearch = () => {
  router.push({ name: 'consulta' })
}

const goToArranchamento = () => {
  router.push({ name: 'arranchamento_public' })
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--secondary-blue) 0%, #c4d7e6 100%);
  padding: 2rem;
}

.login-card {
  width: 100%;
  max-width: 400px;
  padding: 2.5rem;
  text-align: center;
}

.logo {
  width: 100px;
  height: auto;
  margin-bottom: 1rem;
}

.title {
  color: var(--primary-blue);
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.subtitle {
  color: var(--text-muted);
  font-weight: 500;
  margin-bottom: 2rem;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  text-align: left;
}

.input-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
  margin-bottom: 0.5rem;
}

.w-100 {
  width: 100%;
}

.mt-3 {
  margin-top: 1rem;
}

.btn-secondary {
  background-color: transparent;
  color: var(--primary-blue);
  border: 1px solid var(--primary-blue);
}

.btn-secondary:hover {
  background-color: rgba(10, 66, 117, 0.05);
}

.error-msg {
  color: var(--danger);
  font-size: 0.85rem;
  font-weight: 500;
  text-align: center;
}
</style>
