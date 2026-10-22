<template>
  <div class="painel-s1-page">
    <!-- Banner Principal -->
    <div class="page-header-banner">
      <div class="header-left">
        <h2><i class="fas fa-clipboard-list"></i> Controle de Efetivo</h2>
        <p>Resumo de alterações, Ranking de FO+, Livro de Apresentação Digital e Sincronização com a Planilha Geral</p>
      </div>
      <div class="header-filters">
        <select v-model="filtroSubunidade" class="filter-select" @change="carregarResumo">
          <option value="">Todas as Subunidades</option>
          <option v-for="sub in subunidades" :key="sub" :value="sub">{{ sub }}</option>
        </select>
        <select v-model="filtroDias" class="filter-select" @change="carregarResumo">
          <option :value="0">Todo o período</option>
          <option :value="30">Últimos 30 dias</option>
          <option :value="90">Últimos 90 dias</option>
          <option :value="180">Últimos 6 meses</option>
          <option :value="365">Último ano</option>
        </select>
        <button type="button" class="btn-refresh" @click="carregarResumo" :disabled="loading">
          <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i> Atualizar
        </button>
      </div>
    </div>

    <!-- Cards de Resumo S1 -->
    <div class="kpi-grid">
      <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="fas fa-file-alt"></i></div>
        <div class="kpi-info">
          <span class="kpi-val">{{ cards.total_alteracoes || 0 }}</span>
          <span class="kpi-label">Alterações no Período</span>
        </div>
      </div>
      <div class="kpi-card kpi-green">
        <div class="kpi-icon"><i class="fas fa-plus-circle"></i></div>
        <div class="kpi-info">
          <span class="kpi-val">{{ cards.total_fo_positivo || 0 }}</span>
          <span class="kpi-label">FO+ no Período</span>
        </div>
      </div>
      <div class="kpi-card kpi-red">
        <div class="kpi-icon"><i class="fas fa-minus-circle"></i></div>
        <div class="kpi-info">
          <span class="kpi-val">{{ cards.total_fo_negativo || 0 }}</span>
          <span class="kpi-label">FO- no Período</span>
        </div>
      </div>
      <div class="kpi-card kpi-gold">
        <div class="kpi-icon"><i class="fas fa-award"></i></div>
        <div class="kpi-info">
          <span class="kpi-val">{{ cards.total_elogios || 0 }}</span>
          <span class="kpi-label">Elogios</span>
        </div>
      </div>
      <div class="kpi-card kpi-orange">
        <div class="kpi-icon"><i class="fas fa-gavel"></i></div>
        <div class="kpi-info">
          <span class="kpi-val">{{ cards.total_punicoes || 0 }}</span>
          <span class="kpi-label">Punições</span>
        </div>
      </div>
      <div class="kpi-card kpi-teal">
        <div class="kpi-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="kpi-info">
          <span class="kpi-val">{{ Number(cards.total_dispensas || 0) + Number(cards.total_ferias || 0) }}</span>
          <span class="kpi-label">Dispensas / Férias</span>
        </div>
      </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="s1-tabs-bar">
      <button type="button" class="s1-tab" :class="{ active: abaAtiva === 'alteracoes' }" @click="abaAtiva = 'alteracoes'">
        <i class="fas fa-history"></i> Resumo de Alterações S1
      </button>
      <button type="button" class="s1-tab" :class="{ active: abaAtiva === 'ranking' }" @click="abaAtiva = 'ranking'">
        <i class="fas fa-trophy"></i> Ranking de FO+ ({{ rankingFo.length }})
      </button>
      <button type="button" class="s1-tab" :class="{ active: abaAtiva === 'livro' }" @click="abaAtiva = 'livro'">
        <i class="fas fa-book"></i> Livro de Apresentação Digital
      </button>
      <button type="button" class="s1-tab" :class="{ active: abaAtiva === 'planilha' }" @click="abaAtiva = 'planilha'">
        <i class="fas fa-table"></i> Sincronizar Planilha Geral
      </button>
    </div>

    <!-- ABA 1: RESUMO DE ALTERAÇÕES S1 -->
    <div v-if="abaAtiva === 'alteracoes'" class="glass-panel section-panel">
      <div class="panel-header">
        <h5><i class="fas fa-stream"></i> Alterações Registradas no Histórico S1</h5>
        <span class="badge-count">{{ alteracoesRecentes.length }} registro(s)</span>
      </div>

      <div v-if="alteracoesRecentes.length === 0" class="empty-state">
        <i class="fas fa-folder-open"></i>
        <p>Nenhuma alteração registrada no período selecionado.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="s1-table">
          <thead>
            <tr>
              <th>Data</th>
              <th>Militar</th>
              <th>Subunidade</th>
              <th>Categoria / Tipo</th>
              <th>Documento Ref.</th>
              <th>Descrição</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="alt in alteracoesRecentes" :key="alt.id">
              <td class="td-nowrap">{{ formatarData(alt.data_fato) }}</td>
              <td class="td-bold">
                {{ alt.posto_grad }} {{ alt.numero ? alt.numero + ' ' : '' }}{{ alt.nome_guerra }}
              </td>
              <td><span class="sub-badge">{{ alt.subunidade || 'S/C' }}</span></td>
              <td>
                <span class="cat-badge" :class="classeTipoAlt(alt)">
                  {{ alt.categoria }}{{ alt.tipo_detalhe ? ' — ' + alt.tipo_detalhe : '' }}
                </span>
              </td>
              <td>{{ alt.documento_ref || '—' }}</td>
              <td class="td-desc">{{ alt.descricao }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ABA 2: RANKING DE FO+ (EXCLUSIVO SARGENTEAÇÃO / ADMIN) -->
    <div v-if="abaAtiva === 'ranking'" class="glass-panel section-panel">
      <div class="panel-header">
        <div>
          <h5><i class="fas fa-trophy"></i> Ranking Geral de Fatos Observados Positivos (FO+)</h5>
          <small class="text-muted">Visível exclusivamente para Sargenteação e Administração. Militares que atingem múltiplos de 5 FO+ recebem destaque.</small>
        </div>
        <span class="badge-count">{{ rankingFo.length }} militar(es) com FO+</span>
      </div>

      <div v-if="rankingFo.length === 0" class="empty-state">
        <i class="fas fa-award"></i>
        <p>Nenhum militar possui FO+ registrado para o filtro selecionado.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="s1-table">
          <thead>
            <tr>
              <th style="width: 70px;">Posição</th>
              <th>Militar</th>
              <th>Subunidade</th>
              <th>Grupamento (GPT)</th>
              <th>Total FO+</th>
              <th>Status de Meta (5 em 5 FO+)</th>
              <th>Último FO+</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(m, idx) in rankingFo" :key="m.id" :class="{ 'row-highlight-fo': Number(m.total_fo_positivo) >= 5 }">
              <td class="td-pos">
                <span class="pos-circle" :class="'pos-' + (idx + 1)">{{ idx + 1 }}º</span>
              </td>
              <td class="td-bold">
                {{ m.posto_grad }} {{ m.numero ? m.numero + ' ' : '' }}{{ m.nome_guerra }}
                <small class="d-block text-muted">{{ m.nome_completo }}</small>
              </td>
              <td><span class="sub-badge">{{ m.subunidade || 'S/C' }}</span></td>
              <td>{{ m.grupamento || '—' }}</td>
              <td>
                <span class="fo-count-pill">
                  <i class="fas fa-plus-circle"></i> {{ m.total_fo_positivo }} FO+
                </span>
              </td>
              <td>
                <span v-if="Number(m.total_fo_positivo) >= 5 && Number(m.total_fo_positivo) % 5 === 0" class="meta-badge meta-exact">
                  <i class="fas fa-star"></i> Atingiu {{ m.total_fo_positivo }} FO+ (Direito a Dispensa)
                </span>
                <span v-else-if="Number(m.total_fo_positivo) > 5" class="meta-badge meta-passed">
                  <i class="fas fa-check-circle"></i> Meta {{ Math.floor(Number(m.total_fo_positivo) / 5) * 5 }} FO+ alcançada ({{ 5 - (Number(m.total_fo_positivo) % 5) }} p/ próxima)
                </span>
                <span v-else class="meta-badge meta-progress">
                  Faltam {{ 5 - Number(m.total_fo_positivo) }} FO+ para atingir 5
                </span>
              </td>
              <td class="td-nowrap">{{ formatarData(m.ultimo_fo_positivo) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ABA 3: LIVRO DE APRESENTAÇÃO DIGITAL & SITUAÇÃO DO EFETIVO -->
    <div v-if="abaAtiva === 'livro'" class="glass-panel section-panel">
      <div class="panel-header">
        <div>
          <h5><i class="fas fa-book-open"></i> Situação Atual do Efetivo & Livro de Apresentação Digital</h5>
          <small class="text-muted">Controle das 27 situações padronizadas do Batalhão e registros de apresentação dos militares.</small>
        </div>
      </div>

      <!-- Resumo por Situação -->
      <div class="situacoes-chips">
        <div
          v-for="sit in situacoesResumo"
          :key="sit.situacao"
          class="sit-chip"
          :class="{ 'sit-em-forma': sit.situacao === 'Em Forma', 'sit-afastado': sit.situacao !== 'Em Forma' }"
        >
          <span class="sit-name">{{ sit.situacao }}</span>
          <strong class="sit-total">{{ sit.total }}</strong>
        </div>
      </div>

      <hr class="section-divider" />

      <div class="panel-header">
        <h6><i class="fas fa-list-ul"></i> Últimos Registros no Livro de Apresentação</h6>
        <span class="badge-count">{{ livroRecente.length }} apresentação(ões)</span>
      </div>

      <div v-if="livroRecente.length === 0" class="empty-state">
        <i class="fas fa-book"></i>
        <p>Nenhuma apresentação registrada até o momento.</p>
      </div>

      <div v-else class="table-responsive">
        <table class="s1-table">
          <thead>
            <tr>
              <th>Data/Hora Registro</th>
              <th>Militar</th>
              <th>Subunidade</th>
              <th>Mudança de Situação</th>
              <th>Período (Início / Término)</th>
              <th>Observação</th>
              <th>Registrado Por</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="reg in livroRecente" :key="reg.id">
              <td class="td-nowrap">{{ formatarDataHora(reg.created_at) }}</td>
              <td class="td-bold">
                {{ reg.posto_grad }} {{ reg.numero ? reg.numero + ' ' : '' }}{{ reg.nome_guerra }}
              </td>
              <td><span class="sub-badge">{{ reg.subunidade || 'S/C' }}</span></td>
              <td>
                <span class="sit-old">{{ reg.situacao_anterior || 'Em Forma' }}</span>
                <i class="fas fa-arrow-right mx-2 text-muted"></i>
                <span class="sit-new">{{ reg.situacao_nova }}</span>
              </td>
              <td class="td-nowrap">
                <span v-if="reg.data_inicio || reg.data_termino">
                  {{ formatarData(reg.data_inicio) }} até {{ formatarData(reg.data_termino) }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>{{ reg.observacao || '—' }}</td>
              <td>{{ reg.registrado_por || 'Sistema' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ABA 4: SINCRONIZAR PLANILHA GERAL -->
    <div v-if="abaAtiva === 'planilha'" class="glass-panel section-panel">
      <div class="panel-header">
        <div>
          <h5><i class="fas fa-table"></i> Sincronizador com a Planilha Geral do Batalhão</h5>
          <small class="text-muted">
            Cabeçalho padrão reconhecido: <strong>Ord | P/Grad | Nr | QM | GPT | NOME DE GUERRA | NOME COMPLETO | SITUAÇÃO | FRAÇÃO | SEÇÃO | FUNÇÃO | ORD.</strong>
          </small>
        </div>
        <span v-if="ultimaSyncPlanilha" class="sync-time-badge">
          <i class="fas fa-clock"></i> Última sincronização: {{ formatarDataHora(ultimaSyncPlanilha) }}
        </span>
      </div>

      <div class="sync-info-box">
        <i class="fas fa-info-circle"></i>
        <div>
          <strong>Como funciona a sincronização:</strong>
          <ul>
            <li>Compara cada militar da planilha pelo <strong>Nome Completo</strong> com o banco de dados do SISMIL.</li>
            <li>Atualiza automaticamente <strong>Fração (Subunidade/Cia)</strong>, <strong>Situação Atual</strong>, <strong>Grupamento (GPT)</strong>, <strong>Número</strong>, <strong>QM</strong>, <strong>Seção</strong> e <strong>Função</strong> (sem sujar o Histórico S1 com transferências de Cia).</li>
            <li>Quando um militar novo é inserido na planilha e ainda não existe no SISMIL, gera uma notificação no sininho e permite realizar o <strong>Pré-Cadastro com 1 clique</strong>.</li>
          </ul>
        </div>
      </div>

      <div class="sync-methods-grid">
        <!-- Método 1: Link do Google Sheets -->
        <div class="sync-method-card">
          <h6><i class="fas fa-link"></i> Opção 1: Link da Planilha no Google Drive / Sheets</h6>
          <p class="small text-muted">Cole o link de compartilhamento da planilha (qualquer pessoa com o link pode visualizar) ou link de exportação CSV.</p>
          <div class="input-group">
            <input
              type="url"
              v-model="planilhaUrl"
              class="input-modern"
              placeholder="https://docs.google.com/spreadsheets/d/.../edit#gid=0"
            />
          </div>
          <button
            type="button"
            class="btn-modern btn-primary-modern mt-2"
            :disabled="sincronizando || !planilhaUrl"
            @click="executarSyncUrl"
          >
            <i class="fas fa-cloud-download-alt"></i>
            {{ sincronizando ? 'Sincronizando...' : 'Puxar Dados pelo Link' }}
          </button>
        </div>

        <!-- Método 2: Upload de CSV ou Colar da Planilha -->
        <div class="sync-method-card">
          <h6><i class="fas fa-paste"></i> Opção 2: Colar Linhas da Planilha ou Enviar Arquivo CSV</h6>
          <p class="small text-muted">Se a planilha do Drive for restrita, selecione as linhas na planilha (incluindo o cabeçalho), copie (Ctrl+C) e cole abaixo, ou envie o arquivo `.csv`.</p>
          <textarea
            v-model="textoColado"
            rows="4"
            class="input-modern"
            placeholder="Cole aqui os dados copiados da Planilha Geral (com a linha de cabeçalho Ord | P/Grad | Nr | QM | GPT | NOME DE GUERRA | NOME COMPLETO | SITUAÇÃO | FRAÇÃO...)"
          ></textarea>
          <div class="sync-file-row">
            <input type="file" accept=".csv,.tsv,.txt" @change="onArquivoPlanilhaChange" class="input-file-csv" />
            <button
              type="button"
              class="btn-modern btn-primary-modern"
              :disabled="sincronizando || (!textoColado && !arquivoPlanilha)"
              @click="executarSyncTextoOuArquivo"
            >
              <i class="fas fa-check-circle"></i>
              {{ sincronizando ? 'Processando...' : 'Sincronizar Dados Informados' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Resultado da Sincronização -->
      <div v-if="resultadoSync" class="sync-result-panel">
        <div class="sync-summary-bar">
          <div class="sync-stat">
            <strong>{{ resultadoSync.linhas_lidas }}</strong>
            <span>Linhas Lidas</span>
          </div>
          <div class="sync-stat stat-updated">
            <strong>{{ resultadoSync.atualizados }}</strong>
            <span>Militares Atualizados</span>
          </div>
          <div class="sync-stat stat-new">
            <strong>{{ (resultadoSync.novos_na_planilha || []).length }}</strong>
            <span>Novos p/ Pré-Cadastro</span>
          </div>
        </div>

        <!-- Novos Militares Encontrados na Planilha -->
        <div v-if="resultadoSync.novos_na_planilha && resultadoSync.novos_na_planilha.length > 0" class="sync-subbox">
          <h6><i class="fas fa-user-plus"></i> Militares Novos Identificados na Planilha Geral ({{ resultadoSync.novos_na_planilha.length }})</h6>
          <div class="table-responsive">
            <table class="s1-table">
              <thead>
                <tr>
                  <th>P/Grad</th>
                  <th>Nr</th>
                  <th>Nome de Guerra</th>
                  <th>Nome Completo</th>
                  <th>Grupamento (GPT)</th>
                  <th>Fração (Cia)</th>
                  <th>Situação</th>
                  <th>Ação</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(nv, idx) in resultadoSync.novos_na_planilha" :key="idx">
                  <td>{{ nv.posto_grad }}</td>
                  <td>{{ nv.numero || '—' }}</td>
                  <td class="td-bold">{{ nv.nome_guerra }}</td>
                  <td>{{ nv.nome_completo }}</td>
                  <td>{{ nv.grupamento || '—' }}</td>
                  <td><span class="sub-badge">{{ nv.subunidade }}</span></td>
                  <td>{{ nv.situacao_atual }}</td>
                  <td>
                    <button
                      type="button"
                      class="btn-precad-inline"
                      :disabled="nv.cadastrado"
                      @click="efetivarPreCadastroDireto(nv)"
                    >
                      <i :class="nv.cadastrado ? 'fas fa-check' : 'fas fa-user-plus'"></i>
                      {{ nv.cadastrado ? 'Pré-Cadastrado' : 'Efetivar Pré-Cadastro' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Detalhes de Atualizações -->
        <div v-if="resultadoSync.detalhes_mudancas && resultadoSync.detalhes_mudancas.length > 0" class="sync-subbox mt-3">
          <h6><i class="fas fa-exchange-alt"></i> Alterações Aplicadas Automaticamente ({{ resultadoSync.detalhes_mudancas.length }})</h6>
          <div class="table-responsive">
            <table class="s1-table">
              <thead>
                <tr>
                  <th>Militar</th>
                  <th>Nome Completo</th>
                  <th>Campos Atualizados pela Planilha</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in resultadoSync.detalhes_mudancas" :key="item.id">
                  <td class="td-bold">{{ item.militar }}</td>
                  <td>{{ item.nome_completo }}</td>
                  <td>
                    <span v-for="(m, i) in item.mudancas" :key="i" class="change-pill">
                      {{ m }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { PainelS1Service } from '../services/PainelS1Service'
import { useToast } from '../composables/useToast'

const toast = useToast()

const subunidades = [
  'Cmdo', 'EM', 'PMGu', 'Cia E Eqp Mnt', '1ª Cia E Cnst', 'Cia C Ap', '2ª Cia E Cnst', 'NPOR', 'PTTC'
]

const abaAtiva = ref('alteracoes')
const filtroSubunidade = ref('')
const filtroDias = ref(30)
const loading = ref(false)

const cards = ref({})
const alteracoesRecentes = ref([])
const rankingFo = ref([])
const situacoesResumo = ref([])
const livroRecente = ref([])
const planilhaUrl = ref('')
const ultimaSyncPlanilha = ref('')

const textoColado = ref('')
const arquivoPlanilha = ref(null)
const sincronizando = ref(false)
const resultadoSync = ref(null)

const formatarData = (dt) => {
  if (!dt || dt === '0000-00-00') return '—'
  const parts = String(dt).substring(0, 10).split('-')
  if (parts.length !== 3) return dt
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

const formatarDataHora = (dtStr) => {
  if (!dtStr) return ''
  const d = new Date(String(dtStr).replace(' ', 'T'))
  if (isNaN(d.getTime())) return dtStr
  return d.toLocaleDateString('pt-BR') + ' às ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

const classeTipoAlt = (alt) => {
  const det = String(alt.tipo_detalhe || '').toUpperCase()
  const cat = String(alt.categoria || '').toUpperCase()
  if (det === 'FO+' || cat.includes('ELOGIO')) return 'cat-pos'
  if (det === 'FO-' || cat.includes('PUNI')) return 'cat-neg'
  return 'cat-neutral'
}

const carregarResumo = async () => {
  loading.value = true
  try {
    const res = await PainelS1Service.getResumoS1({
      subunidade: filtroSubunidade.value,
      dias: filtroDias.value
    })
    if (res.status === 'sucesso' && res.dados) {
      cards.value = res.dados.cards || {}
      alteracoesRecentes.value = res.dados.alteracoes_recentes || []
      rankingFo.value = res.dados.ranking_fo_positivo || []
      situacoesResumo.value = res.dados.situacoes_resumo || []
      livroRecente.value = res.dados.livro_recente || []
      if (res.dados.planilha_url && !planilhaUrl.value) {
        planilhaUrl.value = res.dados.planilha_url
      }
      ultimaSyncPlanilha.value = res.dados.ultima_sync_planilha || ''
    } else {
      toast.error(res.msg || 'Erro ao carregar dados do Painel S1.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao carregar o Painel S1.')
  } finally {
    loading.value = false
  }
}

const onArquivoPlanilhaChange = (e) => {
  arquivoPlanilha.value = e.target.files && e.target.files[0] ? e.target.files[0] : null
}

const executarSyncUrl = async () => {
  if (!planilhaUrl.value) return
  sincronizando.value = true
  resultadoSync.value = null
  try {
    const res = await PainelS1Service.sincronizarPlanilha({ planilha_url: planilhaUrl.value })
    if (res.status === 'sucesso') {
      resultadoSync.value = res.dados || res
      toast.success(res.message || 'Planilha sincronizada com sucesso!')
      try { await carregarResumo() } catch (err) {}
    } else {
      toast.error(res.msg || res.message || 'Falha ao sincronizar planilha.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao sincronizar com a planilha.')
  } finally {
    sincronizando.value = false
  }
}

const executarSyncTextoOuArquivo = async () => {
  sincronizando.value = true
  resultadoSync.value = null
  try {
    let payload
    if (arquivoPlanilha.value) {
      payload = new FormData()
      payload.append('arquivo_planilha', arquivoPlanilha.value)
      if (planilhaUrl.value) payload.append('planilha_url', planilhaUrl.value)
    } else {
      payload = {
        conteudo_colado: textoColado.value,
        planilha_url: planilhaUrl.value
      }
    }

    const res = await PainelS1Service.sincronizarPlanilha(payload)
    if (res.status === 'sucesso') {
      resultadoSync.value = res.dados || res
      toast.success(res.message || 'Dados sincronizados com sucesso!')
      try { await carregarResumo() } catch (err) {}
    } else {
      toast.error(res.msg || res.message || 'Não foi possível processar os dados da planilha.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao enviar dados da planilha.')
  } finally {
    sincronizando.value = false
  }
}

const efetivarPreCadastroDireto = async (nv) => {
  try {
    const res = await PainelS1Service.efetivarPreCadastro({ dados: nv })
    if (res.status === 'sucesso') {
      nv.cadastrado = true
      toast.success(res.message || 'Pré-cadastro criado com sucesso!')
    } else {
      toast.error(res.msg || res.message || 'Erro ao efetivar pré-cadastro.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao criar pré-cadastro.')
  }
}

onMounted(() => {
  carregarResumo()
})
</script>

<style scoped>
.painel-s1-page {
  padding-bottom: 2.5rem;
}

.page-header-banner {
  background: linear-gradient(120deg, #0a4275 0%, #1e3a8a 100%);
  color: #ffffff;
  border-radius: 16px;
  padding: 1.6rem 2rem;
  margin-bottom: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.25rem;
  flex-wrap: wrap;
  box-shadow: 0 8px 24px rgba(10, 66, 117, 0.18);
}
.header-left h2 {
  margin: 0 0 0.3rem;
  font-size: 1.35rem;
  font-weight: 800;
}
.header-left p {
  margin: 0;
  opacity: 0.85;
  font-size: 0.86rem;
}
.header-filters {
  display: flex;
  gap: 0.65rem;
  align-items: center;
  flex-wrap: wrap;
}
.filter-select {
  background: rgba(255, 255, 255, 0.14);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 8px;
  padding: 0.5rem 0.85rem;
  font-size: 0.84rem;
  font-weight: 600;
}
.filter-select option {
  color: #0f172a;
  background: #ffffff;
}
.btn-refresh {
  background: #ffffff;
  color: var(--primary-blue);
  border: none;
  border-radius: 8px;
  padding: 0.5rem 1rem;
  font-weight: 700;
  font-size: 0.84rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

/* KPI Grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.kpi-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 1.15rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.kpi-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  flex-shrink: 0;
}
.kpi-blue .kpi-icon { background: #e0f2fe; color: #0369a1; }
.kpi-green .kpi-icon { background: #dcfce7; color: #15803d; }
.kpi-red .kpi-icon { background: #fee2e2; color: #b91c1c; }
.kpi-gold .kpi-icon { background: #fef3c7; color: #b45309; }
.kpi-orange .kpi-icon { background: #ffedd5; color: #c2410c; }
.kpi-teal .kpi-icon { background: #ccfbf1; color: #0f766e; }

.kpi-info {
  display: flex;
  flex-direction: column;
}
.kpi-val {
  font-size: 1.45rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
}
.kpi-label {
  font-size: 0.76rem;
  color: #64748b;
  font-weight: 600;
  margin-top: 0.15rem;
}

/* Abas */
.s1-tabs-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.s1-tab {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 0.65rem 1.15rem;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.86rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
}
.s1-tab:hover {
  background: #f1f5f9;
  color: var(--primary-blue);
}
.s1-tab.active {
  background: var(--primary-blue);
  color: #ffffff;
  border-color: var(--primary-blue);
}

.section-panel {
  padding: 1.5rem;
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  gap: 1rem;
  flex-wrap: wrap;
}
.panel-header h5, .panel-header h6 {
  margin: 0;
  font-weight: 800;
  color: var(--primary-blue);
}
.badge-count {
  background: #e0f2fe;
  color: #0369a1;
  padding: 0.25rem 0.75rem;
  border-radius: 999px;
  font-size: 0.78rem;
  font-weight: 700;
}

.s1-table {
  width: 100%;
  border-collapse: collapse;
}
.s1-table th {
  background: #f8fafc;
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0;
}
.s1-table td {
  padding: 0.8rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.86rem;
  vertical-align: middle;
}
.td-nowrap { white-space: nowrap; }
.td-bold { font-weight: 700; color: #0f172a; }
.td-desc { max-width: 380px; color: #334155; }
.sub-badge {
  background: #f1f5f9;
  color: #334155;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
}

.cat-badge {
  display: inline-block;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
}
.cat-pos { background: #dcfce7; color: #15803d; }
.cat-neg { background: #fee2e2; color: #b91c1c; }
.cat-neutral { background: #e0f2fe; color: #0369a1; }

/* Ranking FO+ */
.row-highlight-fo {
  background: #f0fdf4;
}
.pos-circle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #334155;
  font-weight: 800;
  font-size: 0.8rem;
}
.pos-1 { background: #fef08a; color: #854d0e; }
.pos-2 { background: #e2e8f0; color: #1e293b; }
.pos-3 { background: #fed7aa; color: #9a3412; }

.fo-count-pill {
  background: #16a34a;
  color: #ffffff;
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  font-weight: 800;
  font-size: 0.8rem;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.meta-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.65rem;
  border-radius: 6px;
  font-size: 0.76rem;
  font-weight: 700;
}
.meta-exact { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.meta-passed { background: #e0f2fe; color: #075985; }
.meta-progress { background: #f1f5f9; color: #64748b; }

/* Situações Chips */
.situacoes-chips {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}
.sit-chip {
  padding: 0.75rem 1rem;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8fafc;
}
.sit-em-forma {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #166534;
}
.sit-afastado {
  background: #fffbeb;
  border-color: #fde68a;
  color: #92400e;
}
.sit-name { font-size: 0.82rem; font-weight: 700; }
.sit-total { font-size: 1.1rem; font-weight: 800; }

.sit-old {
  background: #f1f5f9;
  color: #64748b;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-size: 0.76rem;
}
.sit-new {
  background: #dbeafe;
  color: #1e40af;
  padding: 0.2rem 0.55rem;
  border-radius: 4px;
  font-size: 0.78rem;
  font-weight: 700;
}

.section-divider {
  border: none;
  border-top: 1px solid #e2e8f0;
  margin: 1.5rem 0;
}

/* Sincronizador Planilha */
.sync-time-badge {
  background: #f1f5f9;
  color: #334155;
  padding: 0.35rem 0.75rem;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
}
.sync-info-box {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e3a8a;
  padding: 1rem 1.25rem;
  border-radius: 10px;
  display: flex;
  gap: 0.85rem;
  align-items: flex-start;
  font-size: 0.83rem;
  margin-bottom: 1.5rem;
}
.sync-info-box i {
  font-size: 1.2rem;
  margin-top: 0.15rem;
}
.sync-info-box ul {
  margin: 0.35rem 0 0;
  padding-left: 1.2rem;
}
.sync-methods-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}
.sync-method-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.sync-method-card h6 {
  margin: 0;
  color: #0f172a;
  font-weight: 700;
}
.sync-file-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 0.35rem;
}
.input-file-csv {
  font-size: 0.8rem;
}

.sync-result-panel {
  margin-top: 1.75rem;
  border-top: 2px solid #e2e8f0;
  padding-top: 1.5rem;
}
.sync-summary-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.sync-stat {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 0.75rem 1.25rem;
  display: flex;
  flex-direction: column;
}
.sync-stat strong { font-size: 1.3rem; color: #0f172a; }
.sync-stat span { font-size: 0.76rem; color: #64748b; font-weight: 600; }
.stat-updated { background: #f0fdf4; border-color: #86efac; }
.stat-new { background: #fffbeb; border-color: #fde68a; }

.sync-subbox {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1rem 1.25rem;
}
.sync-subbox h6 {
  margin: 0 0 0.85rem;
  font-weight: 700;
  color: #0f172a;
}
.btn-precad-inline {
  background: #16a34a;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 0.35rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.btn-precad-inline:disabled {
  background: #94a3b8;
  cursor: default;
}
.change-pill {
  display: inline-block;
  background: #e0f2fe;
  color: #0369a1;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  margin: 0.15rem 0.25rem 0.15rem 0;
}

.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #94a3b8;
}
.empty-state i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  color: #cbd5e1;
}

@media (max-width: 900px) {
  .sync-methods-grid {
    grid-template-columns: 1fr;
  }
}
</style>
