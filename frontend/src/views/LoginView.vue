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
          <i class="fas fa-sign-in-alt"></i> {{ loading ? 'Verificando...' : 'Entrar no Sistema' }}
        </button>

        <button type="button" @click="abrirPrimeiroAcesso" class="btn-modern btn-first-access w-100">
          <i class="fas fa-user-check"></i> Novo Usuário (Primeiro Acesso)
        </button>

        <div class="divider-line">
          <span>Acesso Rápido</span>
        </div>

        <button type="button" @click="goToPublicSearch" class="btn-modern btn-secondary w-100">
          <i class="fas fa-search"></i> Consulta Pública
        </button>

        <button type="button" @click="goToArranchamento" class="btn-modern btn-secondary w-100 btn-rancho">
          <i class="fas fa-utensils"></i> Arranchamento Semanal
        </button>
      </form>
    </div>

    <!-- Modal Novo Usuário / Primeiro Acesso -->
    <Transition name="fade">
      <div v-if="showModalPrimeiroAcesso" class="modal-overlay" @click.self="showModalPrimeiroAcesso = false">
        <div class="modal-box">
          <div class="modal-header">
            <div>
              <h5><i class="fas fa-user-shield"></i> Primeiro Acesso Militar</h5>
              <small>Ative sua conta individual criando sua senha de acesso</small>
            </div>
            <button type="button" class="btn-close-modal" @click="showModalPrimeiroAcesso = false">&times;</button>
          </div>

          <form @submit.prevent="handlePrimeiroAcesso" class="modal-body-form">
            <div class="info-banner">
              <i class="fas fa-info-circle"></i>
              <span>Informe o seu CPF cadastrado na Sargenteação e sua Data de Nascimento para validar sua identidade e cadastrar sua senha.</span>
            </div>

            <div class="input-group">
              <label>CPF *</label>
              <input
                type="text"
                v-model="formPrimeiro.cpf"
                class="input-modern"
                placeholder="000.000.000-00"
                required
                maxlength="14"
                @input="mascaraCPFPrimeiro"
              />
            </div>

            <div class="form-row-2">
              <div class="input-group">
                <label>Data de Nascimento *</label>
                <input
                  type="date"
                  v-model="formPrimeiro.dt_nascimento"
                  class="input-modern"
                />
              </div>
              <div class="input-group">
                <label>Idt Militar (Opcional)</label>
                <input
                  type="text"
                  v-model="formPrimeiro.idt_militar"
                  class="input-modern"
                  placeholder="Caso não tenha Dt Nasc"
                />
              </div>
            </div>

            <div class="form-row-2">
              <div class="input-group">
                <label>Criar Senha (mín. 6 caracteres) *</label>
                <input
                  type="password"
                  v-model="formPrimeiro.nova_senha"
                  class="input-modern"
                  placeholder="••••••••"
                  minlength="6"
                  required
                />
              </div>
              <div class="input-group">
                <label>Confirmar Senha *</label>
                <input
                  type="password"
                  v-model="formPrimeiro.confirmar_senha"
                  class="input-modern"
                  placeholder="••••••••"
                  minlength="6"
                  required
                />
              </div>
            </div>

            <p v-if="erroPrimeiroAcesso" class="error-msg">{{ erroPrimeiroAcesso }}</p>

            <div class="modal-footer-btns">
              <button type="button" class="btn-modern btn-secondary" @click="showModalPrimeiroAcesso = false">
                Cancelar
              </button>
              <button type="submit" class="btn-modern btn-primary-modern" :disabled="loadingPrimeiro">
                <i class="fas fa-check"></i> {{ loadingPrimeiro ? 'Ativando...' : 'Criar Senha e Entrar' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <footer class="login-footer">
      Desenvolvido por <strong>Helton</strong> &nbsp;·&nbsp; © 2026 SISMIL
    </footer>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToast } from '../composables/useToast'
import { AuthService } from '../services/AuthService.js'

const cpf = ref('')
const senha = ref('')
const errorMessage = ref('')
const loading = ref(false)

const showModalPrimeiroAcesso = ref(false)
const loadingPrimeiro = ref(false)
const erroPrimeiroAcesso = ref('')
const formPrimeiro = ref({
  cpf: '',
  dt_nascimento: '',
  idt_militar: '',
  nova_senha: '',
  confirmar_senha: ''
})

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const formatarCpfString = (raw) => {
  let v = String(raw || '').replace(/\D/g, '').substring(0, 11)
  v = v.replace(/(\d{3})(\d)/, '$1.$2')
  v = v.replace(/(\d{3})(\d)/, '$1.$2')
  v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2')
  return v
}

const mascaraCPF = (e) => {
  cpf.value = formatarCpfString(e.target.value)
}

const mascaraCPFPrimeiro = (e) => {
  formPrimeiro.value.cpf = formatarCpfString(e.target.value)
}

const abrirPrimeiroAcesso = () => {
  erroPrimeiroAcesso.value = ''
  formPrimeiro.value = {
    cpf: cpf.value || '',
    dt_nascimento: '',
    idt_militar: '',
    nova_senha: '',
    confirmar_senha: ''
  }
  showModalPrimeiroAcesso.value = true
}

const handleLogin = async () => {
  loading.value = true
  errorMessage.value = ''
  
  try {
    const data = await AuthService.login(cpf.value, senha.value)
    
    if (data.status === 'sucesso') {
      authStore.setSession(data.role, cpf.value, data.csrf_token, data.militar_id)
      toast.success('Login efetuado com sucesso!')
      if (data.role === 'militar') {
        router.push({ name: 'meu_painel' })
      } else {
        router.push({ name: 'dashboard' })
      }
    } else {
      errorMessage.value = data.msg || data.message || 'Erro ao realizar login.'
      if (data.primeiro_acesso) {
        abrirPrimeiroAcesso()
      }
    }
  } catch (err) {
    errorMessage.value = 'Erro de conexão com o servidor.'
  } finally {
    loading.value = false
  }
}

const handlePrimeiroAcesso = async () => {
  erroPrimeiroAcesso.value = ''

  if (formPrimeiro.value.nova_senha !== formPrimeiro.value.confirmar_senha) {
    erroPrimeiroAcesso.value = 'A confirmação de senha não confere.'
    return
  }

  if (formPrimeiro.value.nova_senha.length < 6) {
    erroPrimeiroAcesso.value = 'A senha deve possuir pelo menos 6 caracteres.'
    return
  }

  loadingPrimeiro.value = true
  try {
    const data = await AuthService.primeiroAcesso(formPrimeiro.value)
    if (data.status === 'sucesso') {
      showModalPrimeiroAcesso.value = false
      authStore.setSession(data.role, formPrimeiro.value.cpf, data.csrf_token, data.militar_id)
      toast.success(data.message || 'Senha criada com sucesso!')
      if (data.role === 'militar') {
        router.push({ name: 'meu_painel' })
      } else {
        router.push({ name: 'dashboard' })
      }
    } else {
      erroPrimeiroAcesso.value = data.msg || data.message || 'Não foi possível concluir o primeiro acesso.'
    }
  } catch (err) {
    erroPrimeiroAcesso.value = 'Erro de conexão com o servidor.'
  } finally {
    loadingPrimeiro.value = false
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
  flex-direction: column;
  align-items: center;
  background: linear-gradient(135deg, var(--secondary-blue) 0%, #c4d7e6 100%);
  padding: 1.5rem;
}

.login-card {
  width: 100%;
  max-width: 410px;
  padding: 2.25rem;
  text-align: center;
  margin: auto;
  z-index: 10;
}

.logo {
  width: 95px;
  height: auto;
  margin-bottom: 0.85rem;
}

.title {
  color: var(--primary-blue);
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.subtitle {
  color: var(--text-muted);
  font-weight: 500;
  margin-bottom: 1.75rem;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  text-align: left;
}

.input-group label {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text-main);
  margin-bottom: 0.4rem;
}

.w-100 {
  width: 100%;
}

.btn-first-access {
  background-color: #f0fdf4;
  color: #15803d;
  border: 1px solid #86efac;
  font-weight: 700;
}

.btn-first-access:hover {
  background-color: #dcfce7;
}

.divider-line {
  display: flex;
  align-items: center;
  text-align: center;
  color: #94a3b8;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0.25rem 0;
}

.divider-line::before,
.divider-line::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #cbd5e1;
}

.divider-line span {
  padding: 0 0.75rem;
}

.btn-secondary {
  background-color: transparent;
  color: var(--primary-blue);
  border: 1px solid var(--primary-blue);
}

.btn-secondary:hover {
  background-color: rgba(10, 66, 117, 0.05);
}

.btn-rancho {
  border-color: #f59e0b;
  color: #d97706;
}

.error-msg {
  color: var(--danger);
  font-size: 0.85rem;
  font-weight: 600;
  text-align: center;
  margin: 0;
}

/* Modal Primeiro Acesso */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.modal-box {
  background: #ffffff;
  border-radius: 14px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  overflow: hidden;
  text-align: left;
}

.modal-header {
  background: var(--primary-blue);
  color: #ffffff;
  padding: 1.15rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.modal-header h5 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
}

.modal-header small {
  display: block;
  opacity: 0.85;
  font-size: 0.78rem;
  margin-top: 0.2rem;
}

.btn-close-modal {
  background: none;
  border: none;
  color: #ffffff;
  font-size: 1.6rem;
  line-height: 1;
  cursor: pointer;
  opacity: 0.8;
}

.btn-close-modal:hover {
  opacity: 1;
}

.modal-body-form {
  padding: 1.4rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.info-banner {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e40af;
  padding: 0.75rem 0.9rem;
  border-radius: 8px;
  font-size: 0.8rem;
  display: flex;
  gap: 0.6rem;
  align-items: flex-start;
  line-height: 1.4;
}

.info-banner i {
  margin-top: 0.15rem;
  font-size: 0.95rem;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem;
}

.modal-footer-btns {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 0.5rem;
  border-top: 1px solid #e2e8f0;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

@media (max-width: 520px) {
  .form-row-2 {
    grid-template-columns: 1fr;
  }
  .login-container {
    padding: 1rem 1rem 4rem 1rem;
  }
  .login-card {
    padding: 1.5rem 1rem;
  }
}

.login-footer {
  margin-top: auto;
  padding-top: 1rem;
  font-size: 0.8rem;
  color: #64748b;
  text-align: center;
  z-index: 5;
}
.login-footer strong {
  color: #334155;
  font-weight: 700;
}
</style>
