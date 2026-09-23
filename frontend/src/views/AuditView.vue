<template>
  <div class="audit-page">
    <div class="page-header">
      <div>
        <h4 class="page-title"><i class="fas fa-shield-alt"></i> Trilha de Auditoria</h4>
        <p class="page-sub">Registro de todas as acoes criticas realizadas no sistema (POSIN-EB)</p>
      </div>
    </div>

    <div v-if="!isAdmin" class="access-denied">
      <i class="fas fa-lock fa-3x"></i>
      <h5>Acesso Restrito</h5>
      <p>Esta secao e exclusiva para administradores do sistema.</p>
    </div>

    <template v-else>
      <div class="glass-panel filtros-card">
        <div class="filtros-grid">
          <div class="input-group">
            <label>Tipo de Acao</label>
            <select v-model="filtros.acao" class="input-modern" @change="buscar">
              <option value="">Todas as acoes</option>
              <option v-for="a in tiposAcao" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>
          <div class="input-group">
            <label>Usuario</label>
            <input type="text" v-model="filtros.usuario" class="input-modern" placeholder="Nome do usuario..." @keyup.enter="buscar">
          </div>
          <div class="input-group">
            <label>Data Inicio</label>
            <input type="date" v-model="filtros.data_inicio" class="input-modern" @change="buscar">
          </div>
          <div class="input-group">
            <label>Data Fim</label>
            <input type="date" v-model="filtros.data_fim" class="input-modern" @change="buscar">
          </div>
          <div class="input-group" style="display:flex; align-items:flex-end; gap:0.5rem;">
            <button class="btn-modern btn-primary btn-sm" @click="buscar" :disabled="loading">
              <i class="fas fa-search"></i> Filtrar
            </button>
            <button class="btn-modern btn-secondary-outline btn-sm" @click="limpar">
              <i class="fas fa-times"></i> Limpar
            </button>
          </div>
        </div>
      </div>

      <div class="results-info" v-if="!loading">
        <span>{{ total }} registro(s) encontrado(s)</span>
        <span class="text-muted" style="font-size:0.82rem;">Pagina {{ pagina }} de {{ totalPag }}</span>
      </div>

      <div class="glass-panel table-card">
        <div v-if="loading" class="loading-state">
          <i class="fas fa-spinner fa-spin fa-2x"></i>
          <p>Carregando logs...</p>
        </div>
        <div v-else-if="logs.length === 0" class="empty-state">
          <i class="fas fa-clipboard-list fa-2x opacity-25"></i>
          <p>Nenhum registro encontrado para os filtros aplicados.</p>
        </div>
        <div v-else class="table-responsive">
          <table class="table-modern">
            <thead>
              <tr>
                <th style="width:145px">Data / Hora</th>
                <th style="width:130px">Usuario</th>
                <th style="width:180px">Acao</th>
                <th>Detalhes</th>
                <th style="width:120px">IP</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id">
                <td class="td-mono">{{ formatarData(log.created_at) }}</td>
                <td>{{ log.usuario_nome || '-' }}</td>
                <td><span class="badge-acao" :class="badgeClass(log.acao)">{{ log.acao }}</span></td>
                <td class="td-detalhes" :title="log.detalhes">{{ log.detalhes || '-' }}</td>
                <td class="td-mono">{{ log.ip_address }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="paginacao" v-if="totalPag > 1">
          <button class="btn-pag" @click="irPagina(pagina - 1)" :disabled="pagina <= 1">
            <i class="fas fa-chevron-left"></i> Anterior
          </button>
          <div class="pag-info">{{ pagina }} / {{ totalPag }}</div>
          <button class="btn-pag" @click="irPagina(pagina + 1)" :disabled="pagina >= totalPag">
            Proxima <i class="fas fa-chevron-right"></i>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const isAdmin = computed(() => authStore.isAdmin)

const logs      = ref([])
const tiposAcao = ref([])
const loading   = ref(false)
const total     = ref(0)
const pagina    = ref(1)
const totalPag  = ref(1)
const limite    = 50

const filtros = ref({ acao: '', usuario: '', data_inicio: '', data_fim: '' })

const formatarData = (dt) => {
  if (!dt) return '-'
  const d = new Date(dt)
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

const badgeClass = (acao) => {
  if (!acao) return 'badge-cinza'
  if (['LOGIN_FAIL', 'RATE_LIMIT_BLOCK'].includes(acao)) return 'badge-vermelho'
  if (['LOGIN_SUCCESS'].includes(acao)) return 'badge-verde'
  if (['LOGOUT'].includes(acao)) return 'badge-cinza'
  if (acao.startsWith('DELETE') || acao.includes('EXCLUSAO')) return 'badge-laranja'
  return 'badge-azul'
}

const buscar = async () => {
  if (!isAdmin.value) return
  loading.value = true
  try {
    const p = new URLSearchParams({
      pagina: pagina.value, limite,
      acao: filtros.value.acao,
      usuario: filtros.value.usuario,
      data_inicio: filtros.value.data_inicio,
      data_fim: filtros.value.data_fim
    })
    const res  = await fetch('/sismil/backend/api/auditoria/list?' + p, { credentials: 'include' })
    const json = await res.json()
    if (json.dados) {
      logs.value     = json.dados.logs || []
      total.value    = json.dados.total || 0
      totalPag.value = json.dados.total_pag || 1
    }
  } catch (e) {
    console.error('[Auditoria]', e)
  } finally {
    loading.value = false
  }
}

const buscarAcoes = async () => {
  try {
    const res  = await fetch('/sismil/backend/api/auditoria/acoes', { credentials: 'include' })
    const json = await res.json()
    tiposAcao.value = json.dados?.acoes || []
  } catch (e) { /* silencioso */ }
}

const irPagina = (p) => {
  if (p < 1 || p > totalPag.value) return
  pagina.value = p
  buscar()
}

const limpar = () => {
  filtros.value = { acao: '', usuario: '', data_inicio: '', data_fim: '' }
  pagina.value = 1
  buscar()
}

onMounted(() => {
  if (isAdmin.value) { buscarAcoes(); buscar() }
})
</script>

<style scoped>
.audit-page { max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
.page-title { font-size: 1.4rem; font-weight: 800; color: var(--primary-blue); margin: 0; }
.page-title i { margin-right: 0.5rem; }
.page-sub { font-size: 0.83rem; color: var(--text-muted); margin: 0.2rem 0 0; }
.access-denied { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
.access-denied i { color: #dc3545; margin-bottom: 1rem; }
.filtros-card { padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
.filtros-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; align-items: end; }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem; }
.results-info { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.75rem; padding: 0 0.25rem; }
.table-card { padding: 0; overflow: hidden; }
.table-modern { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.table-modern th, .table-modern td { padding: 0.7rem 0.9rem; border-bottom: 1px solid #e9ecef; text-align: left; }
.table-modern th { background: #f8f9fa; font-weight: 700; font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; }
.table-modern tr:hover td { background: #f8f9fa; }
.td-mono { font-family: monospace; font-size: 0.82rem; white-space: nowrap; color: #555; }
.td-detalhes { max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #555; font-size: 0.82rem; }
.badge-acao { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; white-space: nowrap; text-transform: uppercase; letter-spacing: 0.03em; }
.badge-vermelho { background: #fee2e2; color: #991b1b; }
.badge-verde    { background: #dcfce7; color: #166534; }
.badge-azul     { background: #dbeafe; color: #1e40af; }
.badge-laranja  { background: #ffedd5; color: #9a3412; }
.badge-cinza    { background: #f1f5f9; color: #475569; }
.loading-state, .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
.loading-state i { color: var(--primary-blue); margin-bottom: 0.75rem; }
.paginacao { display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 1rem; border-top: 1px solid #e9ecef; }
.btn-pag { background: transparent; border: 1px solid #dee2e6; color: var(--primary-blue); padding: 0.4rem 0.9rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.4rem; }
.btn-pag:hover:not(:disabled) { background: var(--primary-blue); color: white; border-color: var(--primary-blue); }
.btn-pag:disabled { opacity: 0.4; cursor: not-allowed; }
.pag-info { font-weight: 600; color: var(--text-muted); font-size: 0.85rem; }
@media (max-width: 768px) { .filtros-grid { grid-template-columns: 1fr 1fr; } .td-detalhes { max-width: 150px; } }
</style>