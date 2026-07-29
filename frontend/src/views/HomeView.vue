<template>
  <div class="dashboard-page">

    <!-- Cabeçalho com boas-vindas -->
    <div class="welcome-banner">
      <div class="welcome-left">
        <img :src="'/sismil/uploads/brasao.png'" alt="Brasão" class="welcome-brasao" @error="e => e.target.style.display='none'">
        <div>
          <h2 class="welcome-title">Bem-vindo ao SISMIL</h2>
          <p class="welcome-sub">
            <span class="role-chip" :class="roleChipClass">
              <i :class="roleIcon"></i> {{ roleLabel }}
            </span>
            &nbsp;· {{ dataAtual }}
          </p>
        </div>
      </div>
    </div>

    <!-- ===================== CARDS DE STATS ===================== -->

    <!-- Stats: Admin / Sargenteação — vê tudo -->
    <div v-if="canEdit" class="stats-grid">
      <div class="stat-card card-blue" @click="goTo('militares')">
        <div class="stat-icon-wrap"><i class="fas fa-users"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.militares }}</div>
          <div class="stat-label">Efetivo Ativo</div>
        </div>
      </div>
      <div class="stat-card card-slate">
        <div class="stat-icon-wrap"><i class="fas fa-user-slash"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.inativos }}</div>
          <div class="stat-label">Desligados</div>
        </div>
      </div>
      <div class="stat-card card-teal" @click="goTo('frota')">
        <div class="stat-icon-wrap"><i class="fas fa-car"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.veiculos }}</div>
          <div class="stat-label">Veículos Cadastrados</div>
        </div>
      </div>
      <div class="stat-card card-green" @click="goTo('frota')">
        <div class="stat-icon-wrap"><i class="fas fa-check-double"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.homologados }}</div>
          <div class="stat-label">Homologados S2</div>
        </div>
      </div>
      <div class="stat-card card-amber" @click="goTo('frota', { filtro: 'PENDENTES' })" style="cursor: pointer;">
        <div class="stat-icon-wrap"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.pendentes }}</div>
          <div class="stat-label">Pendentes S2</div>
        </div>
        <div class="stat-pulse" v-if="stats.pendentes > 0"></div>
      </div>
      <div class="stat-card card-purple">
        <div class="stat-icon-wrap"><i class="fas fa-id-card"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.com_cnh }}</div>
          <div class="stat-label">Militares c/ CNH</div>
        </div>
      </div>
    </div>

    <!-- Stats: S2 / Transporte — foco em veículos -->
    <div v-else-if="isS2" class="stats-grid">
      <div class="stat-card card-blue">
        <div class="stat-icon-wrap"><i class="fas fa-users"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.militares }}</div>
          <div class="stat-label">Efetivo Ativo</div>
        </div>
      </div>
      <div class="stat-card card-teal">
        <div class="stat-icon-wrap"><i class="fas fa-car"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.veiculos }}</div>
          <div class="stat-label">Veículos Cadastrados</div>
        </div>
      </div>
      <div class="stat-card card-green">
        <div class="stat-icon-wrap"><i class="fas fa-stamp"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.homologados }}</div>
          <div class="stat-label">Homologados</div>
        </div>
      </div>
      <div class="stat-card card-amber" @click="goTo('frota', { filtro: 'PENDENTES' })" style="cursor: pointer;">
        <div class="stat-icon-wrap"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.pendentes }}</div>
          <div class="stat-label">Aguardando Avaliação</div>
        </div>
        <div class="stat-pulse" v-if="stats.pendentes > 0"></div>
      </div>
      <div class="stat-card card-purple">
        <div class="stat-icon-wrap"><i class="fas fa-id-card"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.com_cnh }}</div>
          <div class="stat-label">Militares c/ CNH</div>
        </div>
      </div>
    </div>

    <!-- Stats: User — apenas efetivo -->
    <div v-else class="stats-grid stats-grid-user">
      <div class="stat-card card-blue">
        <div class="stat-icon-wrap"><i class="fas fa-users"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ stats.militares }}</div>
          <div class="stat-label">Efetivo Ativo</div>
        </div>
      </div>
      <div class="stat-card card-slate">
        <div class="stat-icon-wrap"><i class="fas fa-building"></i></div>
        <div class="stat-body">
          <div class="stat-number">{{ Object.keys(efetivoSU).length }}</div>
          <div class="stat-label">Subunidades</div>
        </div>
      </div>
    </div>

    <!-- ===================== PAINÉIS INFORMATIVOS ===================== -->
    <div class="panels-grid">

      <!-- Efetivo por SU — visível para todos -->
      <div class="glass-panel panel-box panel-efetivo">
        <div class="panel-header">
          <h5><i class="fas fa-sitemap"></i> Efetivo por Subunidade</h5>
          <span class="badge-pill">{{ stats.militares }} total</span>
        </div>
        <div class="efetivo-list" v-if="Object.keys(efetivoSU).length > 0">
          <div class="su-row" v-for="(dados, su) in efetivoSU" :key="su">
            <div class="su-top">
              <span class="su-nome">{{ su }}</span>
              <span class="su-total">{{ dados.total }}</span>
            </div>
            <div class="su-bar-track">
              <div class="su-bar-fill" :style="{ width: calcPct(dados.total, maxSU) + '%' }"></div>
            </div>
            <div class="su-chips">
              <span v-for="d in dados.detalhes" :key="d.posto" class="chip">{{ d.posto }} {{ d.qtd }}</span>
            </div>
          </div>
        </div>
        <div v-else class="empty-state"><i class="fas fa-inbox"></i><br>Sem dados</div>
      </div>

      <!-- Pendentes S2 — visível para admin, sargenteacao, s2 -->
      <div v-if="canEdit || isS2" class="glass-panel panel-box panel-pendentes">
        <div class="panel-header">
          <h5><i class="fas fa-hourglass-half text-amber"></i> Pendentes de Avaliação S2</h5>
          <span class="badge-pill badge-warn">{{ veiculosPendentes.length }}</span>
        </div>
        <div v-if="veiculosPendentes.length > 0" class="pendentes-list">
          <div v-for="v in veiculosPendentes" :key="v.id" class="pendente-row">
            <span class="pendente-placa">{{ v.placa }}</span>
            <div class="pendente-info">
              <div class="pendente-mil">{{ v.posto_grad }} {{ v.nome_guerra }}</div>
              <div class="pendente-mod">{{ v.modelo }} · {{ v.cor }}</div>
              <div class="pendente-obs" v-if="v.observacao_s2 && canEdit">
                <i class="fas fa-comment-dots"></i> {{ v.observacao_s2 }}
              </div>
            </div>
          </div>
        </div>
        <div v-else class="empty-state ok">
          <i class="fas fa-check-circle"></i><br>Nenhum veículo pendente!
        </div>
      </div>

      <!-- CNH por Categoria — visível para admin, sargenteacao, s2 -->
      <div v-if="canEdit || isS2" class="glass-panel panel-box panel-cnh">
        <div class="panel-header">
          <h5><i class="fas fa-id-card"></i> CNH por Categoria</h5>
          <span class="badge-pill">{{ stats.com_cnh }} hab.</span>
        </div>
        <div v-if="cnhCats.length > 0" class="cnh-list">
          <div v-for="c in cnhCats" :key="c.cat" class="cnh-row">
            <div class="cnh-meta">
              <span class="cnh-cat">{{ c.cat }}</span>
              <span class="cnh-nome">{{ nomeCNH(c.cat) }}</span>
            </div>
            <div class="cnh-bar-track">
              <div class="cnh-bar-fill" :style="{ width: calcPct(c.qtd, maxCNH) + '%' }"></div>
            </div>
            <span class="cnh-qtd">{{ c.qtd }}</span>
          </div>
        </div>
        <div v-else class="empty-state"><i class="fas fa-inbox"></i><br>Sem dados de CNH</div>
      </div>

    </div><!-- /panels-grid -->

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router   = useRouter()
const authStore = useAuthStore()

const canEdit = computed(() => authStore.canEdit)
const isS2    = computed(() => authStore.isS2)
const role    = computed(() => authStore.userRole)

const roleLabel = computed(() => {
  const map = { admin: 'Administrador', sargenteacao: 'Sargenteação', s2: 'S2 / Inteligência', user: 'Usuário' }
  return map[role.value] || role.value || 'Usuário'
})
const roleChipClass = computed(() => {
  const map = { admin: 'chip-red', sargenteacao: 'chip-blue', s2: 'chip-green', user: 'chip-gray' }
  return map[role.value] || 'chip-gray'
})
const roleIcon = computed(() => {
  const map = { admin: 'fas fa-shield-alt', sargenteacao: 'fas fa-star', s2: 'fas fa-car', user: 'fas fa-user' }
  return map[role.value] || 'fas fa-user'
})

const dataAtual = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })

const stats = ref({ militares: 0, inativos: 0, veiculos: 0, pendentes: 0, homologados: 0, com_cnh: 0 })
const efetivoSU = ref({})
const veiculosPendentes = ref([])
const cnhCats  = ref([])

const maxSU  = computed(() => Math.max(...Object.values(efetivoSU.value).map(s => s.total), 1))
const maxCNH = computed(() => Math.max(...cnhCats.value.map(c => c.qtd), 1))

const calcPct = (val, max) => Math.round((val / max) * 100)

const nomeCNH = (cat) => {
  const map = { A: 'Moto', B: 'Carro', AB: 'Moto+Carro', C: 'Caminhão', D: 'Ônibus', E: 'Carreta', AD: 'Moto+Ôn.', AE: 'Moto+Car.' }
  return map[cat] || cat
}

const goTo = (name, query = {}) => router.push({ name, query })

import { DashboardService } from '../services/DashboardService.js'

const fetchStats = async () => {
  try {
    const json = await DashboardService.getStats()
    if (json.status === 'sucesso') {
      stats.value.militares  = json.militares  ?? 0
      stats.value.inativos   = json.inativos   ?? 0
      stats.value.veiculos   = json.veiculos   ?? 0
      stats.value.pendentes  = json.pendentes  ?? 0
      stats.value.homologados= json.homologados?? 0
      stats.value.com_cnh    = json.com_cnh    ?? 0
      efetivoSU.value        = json.efetivo_su ?? {}
      veiculosPendentes.value= json.veiculos_pendentes ?? []
      cnhCats.value          = json.cnh_cats   ?? []
    }
  } catch (e) { console.error('Erro ao buscar stats', e) }
}

onMounted(fetchStats)
</script>

<style scoped>
.dashboard-page { padding-bottom: 2.5rem; }

/* ── Welcome Banner ── */
.welcome-banner {
  background: linear-gradient(120deg, var(--primary-blue) 0%, #1976d2 70%, #1e88e5 100%);
  color: white; border-radius: 16px; padding: 1.75rem 2rem;
  margin-bottom: 2rem; box-shadow: 0 6px 24px rgba(10,61,114,.3);
  display: flex; align-items: center;
}
.welcome-left  { display: flex; align-items: center; gap: 1.5rem; }
.welcome-brasao{ height: 56px; filter: drop-shadow(0 2px 6px rgba(0,0,0,.25)); }
.welcome-title { font-size: 1.55rem; font-weight: 800; margin: 0 0 .35rem; }
.welcome-sub   { margin: 0; opacity: .85; font-size: .88rem; display: flex; align-items: center; gap: .4rem; }

.role-chip {
  display: inline-flex; align-items: center; gap: .35rem;
  padding: .2rem .7rem; border-radius: 20px; font-size: .78rem; font-weight: 700;
}
.chip-red   { background: rgba(220,38,38,.25); }
.chip-blue  { background: rgba(255,255,255,.25); }
.chip-green { background: rgba(16,185,129,.25); }
.chip-gray  { background: rgba(255,255,255,.15); }

/* ── Stats Grid ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}
.stats-grid-user {
  grid-template-columns: repeat(2, minmax(180px, 260px));
}

.stat-card {
  border-radius: 14px; padding: 1.4rem 1.25rem;
  color: white; cursor: pointer;
  display: flex; align-items: center; gap: 1rem;
  box-shadow: 0 4px 16px rgba(0,0,0,.13);
  transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden;
}
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(0,0,0,.2); }

.card-blue   { background: linear-gradient(135deg, #0d47a1, #1565c0); }
.card-slate  { background: linear-gradient(135deg, #37474f, #546e7a); }
.card-teal   { background: linear-gradient(135deg, #00695c, #00897b); }
.card-green  { background: linear-gradient(135deg, #2e7d32, #43a047); }
.card-amber  { background: linear-gradient(135deg, #e65100, #f57c00); }
.card-purple { background: linear-gradient(135deg, #4a148c, #7b1fa2); }

.stat-icon-wrap { font-size: 2rem; opacity: .85; flex-shrink: 0; }
.stat-body      { flex: 1; }
.stat-number    { font-size: 2rem; font-weight: 900; line-height: 1; }
.stat-label     { font-size: .71rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; opacity: .85; margin-top: .2rem; }

/* pulse dot */
.stat-pulse {
  position: absolute; top: .75rem; right: .75rem;
  width: 10px; height: 10px; border-radius: 50%;
  background: white; opacity: .7;
  animation: blink 1.4s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:.2} 50%{opacity:.9} }

/* ── Panels Grid ── */
.panels-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr;
  gap: 1.5rem;
}

.panel-box { padding: 1.5rem; }

.panel-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 1.25rem; padding-bottom: .75rem;
  border-bottom: 2px solid #f0f4f8;
}
.panel-header h5 {
  margin: 0; font-weight: 700; color: var(--primary-blue);
  font-size: .95rem; display: flex; align-items: center; gap: .5rem;
}
.text-amber { color: #d97706; }

.badge-pill {
  background: var(--secondary-blue); color: var(--primary-blue);
  padding: .18rem .65rem; border-radius: 20px; font-size: .72rem; font-weight: 700;
}
.badge-warn { background: #fef3c7; color: #92400e; }

.empty-state { text-align: center; color: var(--text-muted); padding: 2rem 1rem; font-size: .9rem; line-height: 1.8; }
.empty-state.ok { color: #15803d; }

/* Efetivo */
.efetivo-list { display: flex; flex-direction: column; gap: 1.1rem; max-height: 360px; overflow-y: auto; padding-right: .25rem; }
.su-row {}
.su-top  { display: flex; justify-content: space-between; align-items: center; margin-bottom: .3rem; }
.su-nome { font-weight: 700; font-size: .88rem; }
.su-total{ background: var(--primary-blue); color: white; font-size: .7rem; font-weight: 800; padding: .12rem .5rem; border-radius: 10px; }
.su-bar-track { height: 7px; background: #e2e8f0; border-radius: 4px; margin-bottom: .4rem; overflow: hidden; }
.su-bar-fill  { height: 100%; background: linear-gradient(90deg, var(--primary-blue), #60a5fa); border-radius: 4px; transition: width .8s; }
.su-chips { display: flex; flex-wrap: wrap; gap: .3rem; }
.chip {
  background: #eff6ff; color: #1e40af; font-size: .68rem;
  padding: .1rem .4rem; border-radius: 4px; white-space: nowrap;
}

/* Pendentes */
.pendentes-list { display: flex; flex-direction: column; gap: .7rem; max-height: 360px; overflow-y: auto; padding-right: .25rem; }
.pendente-row   { display: flex; gap: .75rem; align-items: flex-start; padding-bottom: .7rem; border-bottom: 1px solid #f1f5f9; }
.pendente-placa {
  flex-shrink: 0; background: #fef3c7; border: 2px solid #fbbf24;
  border-radius: 4px; padding: .18rem .5rem; font-family: monospace;
  font-weight: 900; font-size: .85rem; color: #78350f;
}
.pendente-info  { flex: 1; }
.pendente-mil   { font-weight: 700; font-size: .84rem; }
.pendente-mod   { font-size: .75rem; color: var(--text-muted); }
.pendente-obs   { font-size: .72rem; color: #dc2626; margin-top: .2rem; }

/* CNH */
.cnh-list { display: flex; flex-direction: column; gap: .85rem; }
.cnh-row  { display: flex; align-items: center; gap: .75rem; }
.cnh-meta { display: flex; align-items: center; gap: .4rem; min-width: 110px; }
.cnh-cat  { background: var(--primary-blue); color: white; font-size: .7rem; font-weight: 700; padding: .12rem .45rem; border-radius: 4px; }
.cnh-nome { font-size: .76rem; color: var(--text-muted); }
.cnh-bar-track { flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.cnh-bar-fill  { height: 100%; background: linear-gradient(90deg, #7c3aed, #6366f1); border-radius: 4px; transition: width .8s; }
.cnh-qtd { font-size: .8rem; font-weight: 700; min-width: 22px; text-align: right; color: var(--text-main); }

/* Responsive */
@media (max-width: 1200px) {
  .stats-grid { grid-template-columns: repeat(3, 1fr); }
  .panels-grid { grid-template-columns: 1fr 1fr; }
  .panel-efetivo { grid-column: 1 / -1; }
}
@media (max-width: 700px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .panels-grid { grid-template-columns: 1fr; }
  .welcome-brasao { display: none; }
}
</style>
