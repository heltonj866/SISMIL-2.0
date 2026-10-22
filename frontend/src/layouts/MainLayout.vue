<template>
  <div class="app-wrapper">
    <nav class="main-sidebar">
      <div class="sidebar-brand">
        <img :src="'/sismil/uploads/brasao.png'" alt="Logo" class="nav-logo">
        <div class="brand-text">
          <span class="title">2º BECnst</span>
          <span class="subtitle">SISMIL</span>
        </div>
      </div>
      
      <div class="nav-links">
        <!-- Menu exclusivo do Militar (login individual) -->
        <template v-if="isMilitar">
          <router-link to="/meu-painel" class="nav-item">
            <i class="fas fa-id-badge"></i> <span>Meu Painel</span>
          </router-link>
        </template>

        <!-- Menu dos Perfis de Gestão / Consulta -->
        <template v-else>
          <router-link to="/dashboard" class="nav-item">
            <i class="fas fa-home"></i> <span>Início</span>
          </router-link>
          <router-link to="/militares" class="nav-item">
            <i class="fas fa-users"></i> <span>Militares</span>
          </router-link>
          <!-- Controle de Efetivo: somente admin e sargenteacao -->
          <router-link v-if="canViewS1Panel" to="/painel-s1" class="nav-item">
            <i class="fas fa-clipboard-list"></i> <span>Controle de Efetivo</span>
          </router-link>
          <!-- Frota S2: admin, s2 -->
          <router-link v-if="canViewS2" to="/frota" class="nav-item">
            <i class="fas fa-car"></i> <span>Frota S2</span>
          </router-link>
          <router-link to="/arranchamento-painel" class="nav-item" v-if="isEncMat || isAdmin">
            <i class="fas fa-utensils"></i> <span>Arranchamento</span>
          </router-link>
          <!-- Administração: somente admin -->
          <router-link v-if="isAdmin" to="/admin" class="nav-item">
            <i class="fas fa-user-shield"></i> <span>Administração</span>
          </router-link>
          <!-- Auditoria: somente admin -->
          <router-link v-if="isAdmin" to="/auditoria" class="nav-item">
            <i class="fas fa-shield-alt"></i> <span>Auditoria</span>
          </router-link>
        </template>
      </div>

      <div class="user-info">
        <!-- Botão de Notificações (Sininho) exclusivo para Admin e Sargenteação -->
        <button
          v-if="canViewS1Panel"
          type="button"
          class="btn-bell-sidebar"
          @click="toggleNotifDrawer"
          title="Central de Notificações S1"
        >
          <div class="bell-left">
            <i class="fas fa-bell"></i>
            <span>Notificações</span>
          </div>
          <span v-if="naoLidasCount > 0" class="bell-badge">{{ naoLidasCount }}</span>
        </button>

        <div class="role-badge">{{ roleLabel(authStore.userRole) }}</div>
        <button class="btn-logout" @click="handleLogout">
          <i class="fas fa-sign-out-alt"></i> <span>Sair</span>
        </button>
      </div>

      <div class="sidebar-footer">
        <span>Desenvolvido por <strong>Helton</strong></span>
        <span>© 2026 SISMIL</span>
      </div>
    </nav>

    <main class="main-content">
      <!-- Barra superior de alertas rápidos para Admin / Sargenteação -->
      <div v-if="canViewS1Panel" class="top-notif-bar">
        <div class="top-notif-left">
          <span class="sys-tag"><i class="fas fa-shield-alt"></i> SISMIL 2.0 — Intranet 2º BECnst</span>
        </div>
        <div class="top-notif-right">
          <button type="button" class="top-bell-btn" @click="toggleNotifDrawer">
            <i class="fas fa-bell" :class="{ 'bell-ringing': naoLidasCount > 0 }"></i>
            <span>Notificações</span>
            <span v-if="naoLidasCount > 0" class="top-bell-counter">{{ naoLidasCount }}</span>
          </button>
        </div>
      </div>

      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>

    <!-- Gaveta / Modal de Notificações (exclusivo Admin e Sargenteação) -->
    <Transition name="fade">
      <div v-if="showNotifDrawer && canViewS1Panel" class="notif-overlay" @click.self="showNotifDrawer = false">
        <div class="notif-drawer">
          <div class="notif-header">
            <div>
              <h5><i class="fas fa-bell"></i> Central de Notificações</h5>
              <small>{{ naoLidasCount }} alerta(s) não lido(s)</small>
            </div>
            <button type="button" class="btn-close-drawer" @click="showNotifDrawer = false">&times;</button>
          </div>

          <div class="notif-toolbar">
            <div class="notif-tabs">
              <button
                type="button"
                class="ntab"
                :class="{ active: filtroApenasNaoLidas }"
                @click="filtroApenasNaoLidas = true"
              >
                Não lidas ({{ naoLidasCount }})
              </button>
              <button
                type="button"
                class="ntab"
                :class="{ active: !filtroApenasNaoLidas }"
                @click="filtroApenasNaoLidas = false"
              >
                Todas ({{ notificacoes.length }})
              </button>
            </div>
            <button
              v-if="naoLidasCount > 0"
              type="button"
              class="btn-mark-all"
              @click="marcarTodasComoLidas"
            >
              <i class="fas fa-check-double"></i> Marcar todas lidas
            </button>
          </div>

          <div class="notif-list">
            <div v-if="loadingNotif" class="notif-empty">
              <i class="fas fa-spinner fa-spin"></i> Atualizando alertas...
            </div>
            <div v-else-if="notificacoesFiltradas.length === 0" class="notif-empty">
              <i class="fas fa-check-circle"></i>
              <p>Nenhuma notificação pendente neste filtro.</p>
            </div>

            <div
              v-for="n in notificacoesFiltradas"
              :key="n.id"
              class="notif-card"
              :class="[{ unread: Number(n.lida) === 0 }, 'notif-type-' + n.tipo]"
            >
              <div class="notif-icon">
                <i :class="iconePorTipo(n.tipo)"></i>
              </div>
              <div class="notif-body">
                <div class="notif-title-row">
                  <strong>{{ n.titulo }}</strong>
                  <span class="notif-date">{{ formatarDataHora(n.created_at) }}</span>
                </div>
                <p class="notif-msg">{{ n.mensagem }}</p>

                <div class="notif-actions">
                  <!-- Botão de Efetivar Pré-Cadastro quando vem da Planilha Geral -->
                  <button
                    v-if="n.tipo === 'PRE_CADASTRO_PLANILHA'"
                    type="button"
                    class="btn-notif-action btn-precadastro"
                    :disabled="efetivandoId === n.id"
                    @click="efetivarPreCadastroNotif(n)"
                  >
                    <i class="fas fa-user-plus"></i>
                    {{ efetivandoId === n.id ? 'Cadastrando...' : 'Efetivar Pré-Cadastro' }}
                  </button>

                  <button
                    v-if="Number(n.lida) === 0"
                    type="button"
                    class="btn-notif-action btn-read"
                    @click="marcarUmaComoLida(n.id)"
                  >
                    <i class="fas fa-check"></i> Marcar como lida
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToast } from '../composables/useToast'
import { PainelS1Service } from '../services/PainelS1Service'

const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const isAdmin = computed(() => authStore.isAdmin)
const isEncMat = computed(() => authStore.isEncMat)
const isMilitar = computed(() => authStore.isMilitar)
const canViewS2 = computed(() => authStore.canViewS2Report)
const canViewS1Panel = computed(() => authStore.canViewS1Panel)

const notificacoes = ref([])
const naoLidasCount = ref(0)
const showNotifDrawer = ref(false)
const loadingNotif = ref(false)
const filtroApenasNaoLidas = ref(false)
const efetivandoId = ref(null)
let notifTimer = null

const notificacoesFiltradas = computed(() => {
  if (filtroApenasNaoLidas.value) {
    return notificacoes.value.filter(n => Number(n.lida) === 0)
  }
  return notificacoes.value
})

const roleLabel = (r) => {
  const map = {
    admin: 'Administrador',
    sargenteacao: 'Sargenteação',
    s2: 'Inteligência S2',
    enc_mat: 'Enc. Material',
    user: 'Consulta',
    militar: 'Militar'
  }
  return map[r] || r
}

const iconePorTipo = (tipo) => {
  const map = {
    CNH_VENCIDA: 'fas fa-id-card',
    CRLV_VENCIDO: 'fas fa-file-exclamation',
    VEICULO_S2: 'fas fa-car-crash',
    META_FO_POSITIVO: 'fas fa-award',
    PRE_CADASTRO_PLANILHA: 'fas fa-user-plus',
    LIVRO_APRESENTACAO: 'fas fa-book-open'
  }
  return map[tipo] || 'fas fa-bell'
}

const formatarDataHora = (dtStr) => {
  if (!dtStr) return ''
  const d = new Date(dtStr.replace(' ', 'T'))
  if (isNaN(d.getTime())) return dtStr
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

const carregarNotificacoes = async (silencioso = false) => {
  if (!canViewS1Panel.value) return
  if (!silencioso) loadingNotif.value = true
  try {
    const res = await PainelS1Service.getNotificacoes(false)
    if (res.status === 'sucesso') {
      const d = res.dados || res
      notificacoes.value = d.notificacoes || d.itens || []
      naoLidasCount.value = Number(d.nao_lidas ?? 0)
    }
  } catch (e) {
    // Falha silenciosa em background
  } finally {
    if (!silencioso) loadingNotif.value = false
  }
}

const toggleNotifDrawer = () => {
  showNotifDrawer.value = !showNotifDrawer.value
  if (showNotifDrawer.value) {
    carregarNotificacoes(false)
  }
}

const marcarUmaComoLida = async (id) => {
  try {
    const res = await PainelS1Service.marcarNotificacaoLida({ id })
    if (res.status === 'sucesso') {
      const item = notificacoes.value.find(n => n.id === id)
      if (item && Number(item.lida) === 0) {
        item.lida = 1
        naoLidasCount.value = Math.max(0, naoLidasCount.value - 1)
      }
    }
  } catch (e) {
    toast.error('Erro ao atualizar notificação.')
  }
}

const marcarTodasComoLidas = async () => {
  try {
    const res = await PainelS1Service.marcarNotificacaoLida({ todas: true })
    if (res.status === 'sucesso') {
      notificacoes.value.forEach(n => { n.lida = 1 })
      naoLidasCount.value = 0
      toast.success('Todas as notificações foram marcadas como lidas.')
    }
  } catch (e) {
    toast.error('Erro ao marcar notificações.')
  }
}

const efetivarPreCadastroNotif = async (notif) => {
  efetivandoId.value = notif.id
  try {
    const res = await PainelS1Service.efetivarPreCadastro({ notificacao_id: notif.id })
    if (res.status === 'sucesso') {
      toast.success(res.message || 'Pré-cadastro efetivado com sucesso!')
      notif.lida = 1
      await carregarNotificacoes(true)
    } else {
      toast.error(res.msg || res.message || 'Não foi possível efetivar o pré-cadastro.')
    }
  } catch (e) {
    toast.error('Erro de comunicação ao efetivar pré-cadastro.')
  } finally {
    efetivandoId.value = null
  }
}

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  if (canViewS1Panel.value) {
    carregarNotificacoes(true)
    notifTimer = setInterval(() => carregarNotificacoes(true), 60000)
  }
})

onBeforeUnmount(() => {
  if (notifTimer) clearInterval(notifTimer)
})
</script>

<style scoped>
.app-wrapper {
  display: flex;
  min-height: 100vh;
  background-color: var(--background);
}
.main-sidebar {
  width: 250px;
  background: white;
  border-right: 1px solid #e9ecef;
  display: flex;
  flex-direction: column;
  position: fixed;
  height: 100vh;
  z-index: 100;
}
.sidebar-brand {
  display: flex;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
}
.nav-logo { height: 45px; margin-right: 15px; }
.brand-text { display: flex; flex-direction: column; }
.brand-text .title { font-weight: 800; color: var(--primary-blue); font-size: 1.2rem; line-height: 1; }
.brand-text .subtitle { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; letter-spacing: 1px; }

.nav-links {
  flex: 1;
  padding: 1.25rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  overflow-y: auto;
}
.nav-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 600;
  border-radius: var(--radius-md);
  transition: all 0.2s;
}
.nav-item i { width: 25px; font-size: 1.05rem; }
.nav-item:hover {
  background: var(--secondary-blue);
  color: var(--primary-blue);
}
.nav-item.router-link-active {
  background: var(--primary-blue);
  color: white;
}

.user-info {
  padding: 1.15rem 1.25rem;
  border-top: 1px solid #e9ecef;
}

.btn-bell-sidebar {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: var(--primary-blue);
  padding: 0.55rem 0.85rem;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  margin-bottom: 0.75rem;
  transition: all 0.2s;
}
.btn-bell-sidebar:hover {
  background: #eff6ff;
  border-color: #bfdbfe;
}
.bell-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.bell-badge {
  background: #dc2626;
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
}

.role-badge {
  display: inline-block;
  background: #e9ecef;
  color: var(--text-main);
  padding: 0.3rem 0.6rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: bold;
  text-transform: uppercase;
  margin-bottom: 0.75rem;
  width: 100%;
  text-align: center;
}
.btn-logout {
  width: 100%;
  background: transparent;
  border: 1px solid var(--danger);
  color: var(--danger);
  padding: 0.5rem;
  border-radius: var(--radius-md);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-logout:hover {
  background: var(--danger);
  color: white;
}

.sidebar-footer {
  padding: 0.85rem 1.5rem;
  border-top: 1px solid #e9ecef;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
  font-size: 0.72rem;
  color: #adb5bd;
  text-align: center;
  line-height: 1.4;
}
.sidebar-footer strong {
  color: var(--primary-blue);
  font-weight: 700;
}

.main-content {
  flex: 1;
  margin-left: 250px;
  padding: 1.5rem 2rem 2rem;
  overflow-y: auto;
  height: 100vh;
}

/* Barra superior de notificações */
.top-notif-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.6rem 1.15rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}
.sys-tag {
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.sys-tag i {
  color: var(--primary-blue);
}
.top-bell-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #1e293b;
  padding: 0.4rem 0.9rem;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.top-bell-btn:hover {
  background: #eff6ff;
  border-color: var(--primary-blue);
  color: var(--primary-blue);
}
.bell-ringing {
  color: #d97706;
}
.top-bell-counter {
  background: #dc2626;
  color: white;
  font-size: 0.72rem;
  font-weight: 800;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
}

/* Drawer de Notificações */
.notif-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
  z-index: 3000;
  display: flex;
  justify-content: flex-end;
}
.notif-drawer {
  width: 100%;
  max-width: 460px;
  background: #ffffff;
  height: 100vh;
  display: flex;
  flex-direction: column;
  box-shadow: -8px 0 30px rgba(0, 0, 0, 0.2);
}
.notif-header {
  background: var(--primary-blue);
  color: #ffffff;
  padding: 1.25rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.notif-header h5 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
}
.notif-header small {
  display: block;
  opacity: 0.85;
  font-size: 0.78rem;
  margin-top: 0.15rem;
}
.btn-close-drawer {
  background: none;
  border: none;
  color: #ffffff;
  font-size: 1.7rem;
  line-height: 1;
  cursor: pointer;
}

.notif-toolbar {
  padding: 0.75rem 1.25rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.notif-tabs {
  display: flex;
  gap: 0.4rem;
}
.ntab {
  background: #e2e8f0;
  border: none;
  padding: 0.35rem 0.75rem;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
}
.ntab.active {
  background: var(--primary-blue);
  color: #ffffff;
}
.btn-mark-all {
  background: transparent;
  border: 1px solid #cbd5e1;
  color: #334155;
  padding: 0.3rem 0.65rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}
.btn-mark-all:hover {
  background: #e2e8f0;
}

.notif-list {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.notif-empty {
  text-align: center;
  padding: 3rem 1rem;
  color: #94a3b8;
}
.notif-empty i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  color: #cbd5e1;
}

.notif-card {
  display: flex;
  gap: 0.85rem;
  padding: 0.9rem 1rem;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  transition: all 0.2s;
}
.notif-card.unread {
  background: #f0f9ff;
  border-color: #bae6fd;
  border-left: 4px solid var(--primary-blue);
}
.notif-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #e0f2fe;
  color: #0369a1;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1rem;
}
.notif-type-CNH_VENCIDA .notif-icon,
.notif-type-CRLV_VENCIDO .notif-icon,
.notif-type-VEICULO_S2 .notif-icon {
  background: #fee2e2;
  color: #b91c1c;
}
.notif-type-META_FO_POSITIVO .notif-icon {
  background: #dcfce7;
  color: #15803d;
}
.notif-type-PRE_CADASTRO_PLANILHA .notif-icon {
  background: #fef3c7;
  color: #b45309;
}

.notif-body {
  flex: 1;
  min-width: 0;
}
.notif-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}
.notif-title-row strong {
  font-size: 0.86rem;
  color: #0f172a;
}
.notif-date {
  font-size: 0.7rem;
  color: #64748b;
  white-space: nowrap;
}
.notif-msg {
  margin: 0 0 0.6rem;
  font-size: 0.8rem;
  color: #334155;
  line-height: 1.4;
}
.notif-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.btn-notif-action {
  border: none;
  padding: 0.3rem 0.65rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.btn-precadastro {
  background: #16a34a;
  color: #ffffff;
}
.btn-precadastro:hover:not(:disabled) {
  background: #15803d;
}
.btn-read {
  background: #e2e8f0;
  color: #334155;
}
.btn-read:hover {
  background: #cbd5e1;
}

@media (max-width: 768px) {
  .main-sidebar {
    width: 70px;
  }
  .sidebar-brand .brand-text, .nav-item span, .bell-left span, .btn-logout span { display: none; }
  .user-info { padding: 1rem 0.5rem; text-align: center; }
  .role-badge { font-size: 0.6rem; padding: 0.2rem; }
  .btn-logout { font-size: 0.8rem; padding: 0.5rem 0; }
  .main-content { margin-left: 70px; padding: 1rem; }
  .sys-tag { display: none; }
}
</style>
