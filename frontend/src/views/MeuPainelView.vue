<template>
  <div class="meu-painel-page">
    <div v-if="loading" class="loading-box glass-panel">
      <i class="fas fa-spinner fa-spin fa-2x"></i>
      <p>Carregando seu perfil militar...</p>
    </div>

    <template v-else-if="militar">
      <!-- Cabeçalho do Perfil do Militar -->
      <div class="profile-banner">
        <div class="profile-left">
          <div class="profile-photo-wrap">
            <img
              v-if="militar.foto_path && militar.foto_path !== 'sem_foto.png'"
              :src="`/sismil/uploads/${militar.foto_path}`"
              alt="Foto Militar"
              class="profile-photo"
              @error="onPhotoError"
            />
            <div v-else class="profile-photo-placeholder">
              <i class="fas fa-user"></i>
            </div>
          </div>

          <div class="profile-identity">
            <div class="badges-row">
              <span class="badge-cia"><i class="fas fa-flag"></i> {{ militar.subunidade || 'Sem Cia' }}</span>
              <span v-if="militar.grupamento" class="badge-gpt"><i class="fas fa-layer-group"></i> GPT: {{ militar.grupamento }}</span>
              <span class="badge-situacao" :class="militar.situacao_atual === 'Em Forma' ? 'sit-ok' : 'sit-away'">
                <i class="fas fa-user-check"></i> Situação: {{ militar.situacao_atual || 'Em Forma' }}
              </span>
            </div>
            <h2>{{ militar.posto_grad }} {{ militar.numero ? militar.numero + ' ' : '' }}{{ militar.nome_guerra }}</h2>
            <p class="full-name">{{ militar.nome_completo }}</p>
            <div class="sub-meta">
              <span v-if="militar.qmg"><i class="fas fa-id-card-alt"></i> QM: {{ militar.qmg }}</span>
              <span v-if="militar.secao"><i class="fas fa-sitemap"></i> Seção: {{ militar.secao }}</span>
              <span v-if="militar.funcao"><i class="fas fa-briefcase"></i> Função: {{ militar.funcao }}</span>
            </div>
          </div>
        </div>

        <!-- Card Individual de FO+ -->
        <div class="fo-personal-card">
          <div class="fo-card-top">
            <i class="fas fa-award"></i>
            <span>Meus FO+ Acumulados</span>
          </div>
          <div class="fo-card-number">{{ meuFoPositivo }} <small>FO+</small></div>
          <div class="fo-progress-bar">
            <div class="fo-progress-fill" :style="{ width: progressoMetaFo + '%' }"></div>
          </div>
          <small class="fo-card-hint">
            <template v-if="meuFoPositivo > 0 && meuFoPositivo % 5 === 0">
              Meta de {{ meuFoPositivo }} FO+ atingida!
            </template>
            <template v-else>
              Faltam {{ 5 - (meuFoPositivo % 5) }} FO+ para completar {{ (Math.floor(meuFoPositivo / 5) + 1) * 5 }} FO+
            </template>
          </small>
        </div>
      </div>

      <!-- Abas do Portal do Militar -->
      <div class="portal-tabs">
        <button type="button" class="ptab" :class="{ active: aba === 'livro' }" @click="aba = 'livro'">
          <i class="fas fa-book-open"></i> Livro de Apresentação Digital
        </button>
        <button type="button" class="ptab" :class="{ active: aba === 'dados' }" @click="aba = 'dados'">
          <i class="fas fa-user-edit"></i> Meus Dados & Veículos
        </button>
        <button type="button" class="ptab" :class="{ active: aba === 'arranchamento' }" @click="aba = 'arranchamento'">
          <i class="fas fa-utensils"></i> Meu Arranchamento
        </button>
        <button type="button" class="ptab" :class="{ active: aba === 'historico' }" @click="aba = 'historico'">
          <i class="fas fa-star"></i> Meus FO+ e Alterações
        </button>
      </div>

      <!-- ABA 1: LIVRO DE APRESENTAÇÃO DIGITAL -->
      <div v-if="aba === 'livro'" class="portal-grid-2">
        <div class="glass-panel portal-box">
          <h5><i class="fas fa-pen-fancy"></i> Registrar Apresentação / Atualizar Situação</h5>
          <p class="box-subtitle">
            Utilize este livro digital para registrar início ou término de férias, dispensas, missões ou retorno à situação "Em Forma". A Sargenteação receberá a notificação automaticamente.
          </p>

          <form @submit.prevent="salvarApresentacao" class="portal-form">
            <div class="input-group">
              <label>Nova Situação *</label>
              <select v-model="formLivro.situacao_nova" class="input-modern" required>
                <option value="">Selecione a situação...</option>
                <option v-for="sit in situacoesDisponiveis" :key="sit" :value="sit">{{ sit }}</option>
              </select>
            </div>

            <div class="form-row-2">
              <div class="input-group">
                <label>Data de Início (Opcional)</label>
                <input type="date" v-model="formLivro.data_inicio" class="input-modern" />
              </div>
              <div class="input-group">
                <label>Data de Término / Retorno (Opcional)</label>
                <input type="date" v-model="formLivro.data_termino" class="input-modern" />
              </div>
            </div>

            <div class="input-group">
              <label>Motivo / Observação de Apresentação</label>
              <textarea
                v-model="formLivro.observacao"
                rows="3"
                class="input-modern"
                placeholder="Ex: Apresentação por início de férias relativas ao ano de 2025 / Retorno de missão OM pronto para o serviço."
              ></textarea>
            </div>

            <button type="submit" class="btn-modern btn-primary-modern" :disabled="salvandoLivro">
              <i class="fas fa-check-circle"></i>
              {{ salvandoLivro ? 'Registrando...' : 'Registrar no Livro Digital' }}
            </button>
          </form>
        </div>

        <div class="glass-panel portal-box">
          <h5><i class="fas fa-history"></i> Minhas Últimas Apresentações</h5>
          <div v-if="meuLivro.length === 0" class="empty-mini">
            <i class="fas fa-book"></i>
            <p>Nenhum registro anterior no Livro de Apresentação.</p>
          </div>
          <div v-else class="livro-timeline">
            <div v-for="item in meuLivro" :key="item.id" class="timeline-item">
              <div class="tl-top">
                <span class="tl-change">
                  <strong>{{ item.situacao_anterior || 'Em Forma' }}</strong>
                  <i class="fas fa-arrow-right mx-1"></i>
                  <strong class="text-primary">{{ item.situacao_nova }}</strong>
                </span>
                <span class="tl-date">{{ formatarDataHora(item.created_at) }}</span>
              </div>
              <div v-if="item.data_inicio || item.data_termino" class="tl-period">
                <i class="fas fa-calendar-alt"></i>
                Período: {{ formatarData(item.data_inicio) }} até {{ formatarData(item.data_termino) }}
              </div>
              <p v-if="item.observacao" class="tl-obs">{{ item.observacao }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ABA 2: MEUS DADOS BÁSICOS (EDITÁVEIS) + CNH/VEÍCULOS (SOMENTE LEITURA) -->
      <div v-if="aba === 'dados'" class="portal-stack">
        <!-- Dados de Contato e Endereço Editáveis -->
        <div class="glass-panel portal-box">
          <div class="box-header-flex">
            <div>
              <h5><i class="fas fa-address-book"></i> Meus Dados de Contato e Endereço</h5>
              <small class="text-muted">Você pode atualizar seu telefone, contato de emergência e endereço residencial a qualquer momento.</small>
            </div>
          </div>

          <form @submit.prevent="salvarMeusDados" class="portal-form mt-3">
            <div class="form-grid-3">
              <div class="input-group">
                <label>E-mail</label>
                <input type="email" v-model="formDados.email" class="input-modern" placeholder="seuemail@exemplo.com" />
              </div>
              <div class="input-group">
                <label>Celular Principal (WhatsApp)</label>
                <input type="text" v-model="formDados.celular_princ" class="input-modern" maxlength="16" @input="mascaraTel($event, 'celular_princ')" placeholder="(00) 00000-0000" />
              </div>
              <div class="input-group">
                <label>Celular Secundário</label>
                <input type="text" v-model="formDados.celular_sec" class="input-modern" maxlength="16" @input="mascaraTel($event, 'celular_sec')" placeholder="(00) 00000-0000" />
              </div>
              <div class="input-group">
                <label>Nome do Contato de Emergência</label>
                <input type="text" v-model="formDados.nome_resp" class="input-modern" placeholder="Familiar / Responsável" />
              </div>
              <div class="input-group">
                <label>Telefone de Emergência 1</label>
                <input type="text" v-model="formDados.tel_resp" class="input-modern" maxlength="16" @input="mascaraTel($event, 'tel_resp')" placeholder="(00) 00000-0000" />
              </div>
              <div class="input-group">
                <label>Telefone de Emergência 2</label>
                <input type="text" v-model="formDados.tel_emergencia" class="input-modern" maxlength="16" @input="mascaraTel($event, 'tel_emergencia')" placeholder="(00) 00000-0000" />
              </div>
            </div>

            <hr class="section-hr" />

            <div class="form-grid-3">
              <div class="input-group">
                <label>CEP</label>
                <div class="cep-row">
                  <input type="text" v-model="formDados.cep" class="input-modern" maxlength="9" placeholder="00000-000" @input="onCepInput" />
                  <button type="button" class="btn-cep" @click="buscarCep"><i class="fas fa-search"></i></button>
                </div>
              </div>
              <div class="input-group" style="grid-column: span 2;">
                <label>Logradouro (Rua / Av.)</label>
                <input type="text" v-model="formDados.endereco" class="input-modern" />
              </div>
              <div class="input-group">
                <label>Número</label>
                <input type="text" v-model="formDados.num_residencia" class="input-modern" />
              </div>
              <div class="input-group">
                <label>Bairro</label>
                <input type="text" v-model="formDados.bairro" class="input-modern" />
              </div>
              <div class="input-group">
                <label>Cidade / UF</label>
                <div class="city-uf-row">
                  <input type="text" v-model="formDados.cidade" class="input-modern" placeholder="Cidade" />
                  <input type="text" v-model="formDados.estado" class="input-modern uf-input" maxlength="2" placeholder="UF" />
                </div>
              </div>
            </div>

            <div class="form-actions-right">
              <button type="submit" class="btn-modern btn-primary-modern" :disabled="salvandoDados">
                <i class="fas fa-save"></i> {{ salvandoDados ? 'Salvando...' : 'Salvar Meus Dados de Contato' }}
              </button>
            </div>
          </form>
        </div>

        <!-- CNH e Veículos (Somente Leitura) -->
        <div class="glass-panel portal-box">
          <div class="box-header-flex">
            <div>
              <h5><i class="fas fa-car"></i> Minha CNH e Veículos (Somente Leitura)</h5>
              <small class="text-muted">Para alterar dados de CNH ou cadastrar novos veículos, procure a Sargenteação da sua Subunidade ou a S2.</small>
            </div>
            <span class="readonly-pill"><i class="fas fa-lock"></i> Somente Leitura</span>
          </div>

          <div class="cnh-summary-bar mt-3">
            <div>
              <strong>Categoria CNH:</strong> {{ militar.cat_cnh || 'Não cadastrada' }}
            </div>
            <div>
              <strong>Validade CNH:</strong> {{ formatarData(militar.validade_cnh) }}
            </div>
          </div>

          <div v-if="meusVeiculos.length === 0" class="empty-mini">
            <i class="fas fa-car-side"></i>
            <p>Nenhum veículo vinculado ao seu cadastro.</p>
          </div>
          <div v-else class="table-responsive mt-3">
            <table class="portal-table">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Placa</th>
                  <th>Marca / Modelo</th>
                  <th>Cor</th>
                  <th>Validade CRLV</th>
                  <th>Status S2</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="v in meusVeiculos" :key="v.id">
                  <td>{{ v.tipo_veiculo }}</td>
                  <td class="td-bold">{{ v.placa }}</td>
                  <td>{{ v.marca }} / {{ v.modelo }}</td>
                  <td>{{ v.cor }}</td>
                  <td>{{ formatarData(v.validade_crlv) }}</td>
                  <td>
                    <span v-if="Number(v.homologado) === 1" class="status-pill pill-ok">
                      <i class="fas fa-check-circle"></i> Homologado S2
                    </span>
                    <span v-else class="status-pill pill-warn">
                      <i class="fas fa-clock"></i> Pendente / Retido S2
                      <small v-if="v.observacao_s2" class="d-block">{{ v.observacao_s2 }}</small>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ABA 3: MEU ARRANCHAMENTO -->
      <div v-if="aba === 'arranchamento'" class="portal-grid-2">
        <div class="glass-panel portal-box">
          <h5><i class="fas fa-utensils"></i> Realizar Meu Arranchamento Semanal</h5>
          <p class="box-subtitle">
            Selecione abaixo os dias e as refeições (Café e Almoço) em que você irá arranchar no Batalhão.
          </p>

          <div class="dias-arranchamento-list">
            <div v-for="(dia, idx) in diasSemanaArranchamento" :key="dia.data" class="dia-card">
              <div class="dia-info">
                <strong>{{ dia.labelDia }}</strong>
                <span>{{ formatarData(dia.data) }}</span>
              </div>
              <div class="dia-checks">
                <label class="meal-check">
                  <input type="checkbox" v-model="dia.cafe" />
                  <span><i class="fas fa-coffee"></i> Café</span>
                </label>
                <label class="meal-check">
                  <input type="checkbox" v-model="dia.almoco" />
                  <span><i class="fas fa-drumstick-bite"></i> Almoço</span>
                </label>
              </div>
            </div>
          </div>

          <button
            type="button"
            class="btn-modern btn-primary-modern w-100 mt-3"
            :disabled="salvandoArranchamento"
            @click="confirmarMeuArranchamento"
          >
            <i class="fas fa-check"></i>
            {{ salvandoArranchamento ? 'Registrando Arranchamento...' : 'Confirmar Meu Arranchamento' }}
          </button>
        </div>

        <div class="glass-panel portal-box">
          <h5><i class="fas fa-calendar-check"></i> Meus Dias Já Arranchados</h5>
          <div v-if="meusArranchamentos.length === 0" class="empty-mini">
            <i class="fas fa-calendar"></i>
            <p>Nenhum arranchamento futuro registrado em seu nome.</p>
          </div>
          <div v-else class="table-responsive">
            <table class="portal-table">
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Café da Manhã</th>
                  <th>Almoço</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="arr in meusArranchamentos" :key="arr.id">
                  <td class="td-bold">{{ formatarData(arr.data_refeicao) }}</td>
                  <td>
                    <span :class="Number(arr.cafe) === 1 ? 'meal-yes' : 'meal-no'">
                      <i :class="Number(arr.cafe) === 1 ? 'fas fa-check' : 'fas fa-times'"></i>
                      {{ Number(arr.cafe) === 1 ? 'Arranchado' : 'Não' }}
                    </span>
                  </td>
                  <td>
                    <span :class="Number(arr.almoco) === 1 ? 'meal-yes' : 'meal-no'">
                      <i :class="Number(arr.almoco) === 1 ? 'fas fa-check' : 'fas fa-times'"></i>
                      {{ Number(arr.almoco) === 1 ? 'Arranchado' : 'Não' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ABA 4: MEUS FO+ E HISTÓRICO INDIVIDUAL -->
      <div v-if="aba === 'historico'" class="glass-panel portal-box">
        <div class="box-header-flex">
          <div>
            <h5><i class="fas fa-star"></i> Meu Histórico de Alterações e FO+</h5>
            <small class="text-muted">Você visualiza exclusivamente os fatos observados e alterações registrados na sua ficha individual.</small>
          </div>
          <span class="fo-badge-highlight">
            <i class="fas fa-plus-circle"></i> Total de FO+: {{ meuFoPositivo }}
          </span>
        </div>

        <div v-if="meuHistorico.length === 0" class="empty-mini mt-3">
          <i class="fas fa-folder-open"></i>
          <p>Nenhuma alteração registrada na sua ficha até o momento.</p>
        </div>

        <div v-else class="table-responsive mt-3">
          <table class="portal-table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Categoria / Tipo</th>
                <th>Documento Ref.</th>
                <th>Descrição</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in meuHistorico" :key="h.id">
                <td class="td-nowrap">{{ formatarData(h.data_fato) }}</td>
                <td>
                  <span class="status-pill" :class="String(h.tipo_detalhe).toUpperCase() === 'FO+' ? 'pill-ok' : 'pill-neutral'">
                    {{ h.categoria }}{{ h.tipo_detalhe ? ' — ' + h.tipo_detalhe : '' }}
                  </span>
                </td>
                <td>{{ h.documento_ref || '—' }}</td>
                <td>{{ h.descricao }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { PainelS1Service } from '../services/PainelS1Service'
import { ArranchamentoService } from '../services/ArranchamentoService'
import { useToast } from '../composables/useToast'

const toast = useToast()

const loading = ref(true)
const aba = ref('livro')

const militar = ref(null)
const meuFoPositivo = ref(0)
const meuHistorico = ref([])
const meusVeiculos = ref([])
const meuLivro = ref([])
const meusArranchamentos = ref([])
const situacoesDisponiveis = ref([])

const salvandoLivro = ref(false)
const formLivro = ref({
  situacao_nova: 'Em Forma',
  data_inicio: '',
  data_termino: '',
  observacao: ''
})

const salvandoDados = ref(false)
const formDados = ref({
  email: '',
  celular_princ: '',
  celular_sec: '',
  nome_resp: '',
  tel_resp: '',
  tel_emergencia: '',
  cep: '',
  endereco: '',
  num_residencia: '',
  bairro: '',
  cidade: '',
  estado: ''
})

const salvandoArranchamento = ref(false)
const diasSemanaArranchamento = ref([])

const progressoMetaFo = computed(() => {
  const resto = meuFoPositivo.value % 5
  if (meuFoPositivo.value > 0 && resto === 0) return 100
  return Math.round((resto / 5) * 100)
})

const onPhotoError = (e) => {
  e.target.style.display = 'none'
}

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
  return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

const gerarDiasArranchamento = () => {
  const nomesDias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado']
  const lista = []
  const hoje = new Date()
  for (let i = 1; i <= 10; i++) {
    const d = new Date(hoje)
    d.setDate(hoje.getDate() + i)
    const dow = d.getDay()
    if (dow === 0 || dow === 6) continue // Foco nos dias úteis da semana
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    const dataIso = `${yyyy}-${mm}-${dd}`

    const existente = meusArranchamentos.value.find(a => a.data_refeicao === dataIso)
    lista.push({
      data: dataIso,
      labelDia: nomesDias[dow],
      cafe: existente ? Number(existente.cafe) === 1 : true,
      almoco: existente ? Number(existente.almoco) === 1 : true
    })
    if (lista.length >= 5) break
  }
  diasSemanaArranchamento.value = lista
}

const carregarMeuPerfil = async () => {
  loading.value = true
  try {
    const res = await PainelS1Service.getMeuPerfil()
    if (res.status === 'sucesso' && res.dados) {
      const d = res.dados
      militar.value = d.militar
      meuFoPositivo.value = Number(d.meu_fo_positivo || 0)
      meuHistorico.value = d.meu_historico || []
      meusVeiculos.value = d.meus_veiculos || []
      meuLivro.value = d.meu_livro || []
      meusArranchamentos.value = d.meus_arranchamentos || []
      situacoesDisponiveis.value = d.situacoes_disponiveis || []

      formLivro.value.situacao_nova = d.militar.situacao_atual || 'Em Forma'

      formDados.value = {
        email: d.militar.email || '',
        celular_princ: d.militar.celular_princ || '',
        celular_sec: d.militar.celular_sec || '',
        nome_resp: d.militar.nome_resp || '',
        tel_resp: d.militar.tel_resp || '',
        tel_emergencia: d.militar.tel_emergencia || '',
        cep: d.militar.cep || '',
        endereco: d.militar.endereco || '',
        num_residencia: d.militar.num_residencia || '',
        bairro: d.militar.bairro || '',
        cidade: d.militar.cidade || '',
        estado: d.militar.estado || ''
      }

      gerarDiasArranchamento()
    } else {
      toast.error(res.msg || 'Não foi possível carregar seu perfil.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao carregar seu painel.')
  } finally {
    loading.value = false
  }
}

const salvarApresentacao = async () => {
  if (!formLivro.value.situacao_nova) {
    toast.warning('Selecione a situação.')
    return
  }
  salvandoLivro.value = true
  try {
    const res = await PainelS1Service.registrarApresentacao(formLivro.value)
    if (res.status === 'sucesso') {
      toast.success(res.message || 'Apresentação registrada com sucesso!')
      formLivro.value.observacao = ''
      await carregarMeuPerfil()
    } else {
      toast.error(res.msg || res.message || 'Erro ao registrar apresentação.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao registrar apresentação.')
  } finally {
    salvandoLivro.value = false
  }
}

const mascaraTel = (e, campo) => {
  let v = e.target.value.replace(/\D/g, '').substring(0, 11)
  v = v.replace(/^(\d{2})(\d)/g, '($1) $2')
  v = v.replace(/(\d)(\d{4})$/, '$1-$2')
  formDados.value[campo] = v
}

const onCepInput = (e) => {
  let v = e.target.value.replace(/\D/g, '').substring(0, 8)
  if (v.length > 5) v = v.replace(/^(\d{5})(\d)/, '$1-$2')
  formDados.value.cep = v
  if (v.replace(/\D/g, '').length === 8) {
    buscarCep()
  }
}

const buscarCep = async () => {
  const cepClean = String(formDados.value.cep || '').replace(/\D/g, '')
  if (cepClean.length !== 8) return
  try {
    const res = await fetch(`/sismil/backend/api/cep?cep=${cepClean}`, { credentials: 'include' })
    const json = await res.json()
    if (json.status === 'sucesso' && json.dados) {
      formDados.value.endereco = json.dados.logradouro || formDados.value.endereco
      formDados.value.bairro = json.dados.bairro || formDados.value.bairro
      formDados.value.cidade = json.dados.localidade || formDados.value.cidade
      formDados.value.estado = json.dados.uf || formDados.value.estado
    }
  } catch (e) {}
}

const salvarMeusDados = async () => {
  salvandoDados.value = true
  try {
    const res = await PainelS1Service.salvarDadosBasicos(formDados.value)
    if (res.status === 'sucesso') {
      toast.success(res.message || 'Dados atualizados com sucesso!')
      await carregarMeuPerfil()
    } else {
      toast.error(res.msg || res.message || 'Erro ao salvar dados.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao salvar seus dados.')
  } finally {
    salvandoDados.value = false
  }
}

const confirmarMeuArranchamento = async () => {
  if (!militar.value) return
  salvandoArranchamento.value = true
  try {
    const payload = {
      subunidade: militar.value.subunidade,
      posto_grad: militar.value.posto_grad,
      numero: militar.value.numero || '',
      nome_guerra: militar.value.nome_guerra,
      refeicoes: diasSemanaArranchamento.value.map(d => ({
        data: d.data,
        cafe: d.cafe ? 1 : 0,
        almoco: d.almoco ? 1 : 0
      }))
    }
    const res = await ArranchamentoService.salvarArranchamento(payload)
    if (res.status === 'sucesso') {
      toast.success(res.message || 'Arranchamento registrado com sucesso!')
      await carregarMeuPerfil()
    } else {
      toast.error(res.msg || res.message || 'Erro ao registrar arranchamento.')
    }
  } catch (e) {
    toast.error('Erro de conexão ao registrar arranchamento.')
  } finally {
    salvandoArranchamento.value = false
  }
}

onMounted(() => {
  carregarMeuPerfil()
})
</script>

<style scoped>
.meu-painel-page {
  padding-bottom: 2.5rem;
}

.loading-box {
  padding: 3rem;
  text-align: center;
  color: var(--primary-blue);
}

.profile-banner {
  background: linear-gradient(125deg, #0a4275 0%, #1e293b 100%);
  color: #ffffff;
  border-radius: 16px;
  padding: 1.75rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
  box-shadow: 0 8px 25px rgba(15, 23, 42, 0.2);
}

.profile-left {
  display: flex;
  align-items: center;
  gap: 1.4rem;
  flex-wrap: wrap;
}

.profile-photo-wrap {
  width: 84px;
  height: 108px;
  border-radius: 10px;
  overflow: hidden;
  border: 2px solid rgba(255, 255, 255, 0.35);
  background: rgba(255, 255, 255, 0.1);
  flex-shrink: 0;
}
.profile-photo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.profile-photo-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.2rem;
  color: rgba(255, 255, 255, 0.6);
}

.badges-row {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 0.45rem;
}
.badge-cia, .badge-gpt, .badge-situacao {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.2rem 0.65rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.badge-cia { background: rgba(255, 255, 255, 0.18); color: #ffffff; }
.badge-gpt { background: rgba(56, 189, 248, 0.25); color: #e0f2fe; }
.sit-ok { background: #16a34a; color: #ffffff; }
.sit-away { background: #f59e0b; color: #ffffff; }

.profile-identity h2 {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 800;
}
.full-name {
  margin: 0.15rem 0 0.45rem;
  font-size: 0.88rem;
  opacity: 0.85;
}
.sub-meta {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  font-size: 0.78rem;
  opacity: 0.85;
}
.sub-meta span {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.fo-personal-card {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 14px;
  padding: 1rem 1.35rem;
  min-width: 240px;
}
.fo-card-top {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  opacity: 0.9;
}
.fo-card-number {
  font-size: 2rem;
  font-weight: 900;
  margin: 0.25rem 0;
}
.fo-card-number small {
  font-size: 0.9rem;
  font-weight: 700;
  opacity: 0.85;
}
.fo-progress-bar {
  height: 7px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 999px;
  overflow: hidden;
  margin-bottom: 0.35rem;
}
.fo-progress-fill {
  height: 100%;
  background: #4ade80;
  border-radius: 999px;
}
.fo-card-hint {
  font-size: 0.74rem;
  opacity: 0.9;
}

/* Abas */
.portal-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.ptab {
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
.ptab.active {
  background: var(--primary-blue);
  color: #ffffff;
  border-color: var(--primary-blue);
}

.portal-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}
.portal-stack {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}
.portal-box {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 1.5rem;
}
.portal-box h5 {
  margin: 0 0 0.25rem;
  color: var(--primary-blue);
  font-weight: 800;
}
.box-subtitle {
  font-size: 0.83rem;
  color: #64748b;
  margin-bottom: 1.25rem;
}

.portal-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem;
}
.form-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.9rem;
}
.input-group label {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 0.35rem;
}
.cep-row, .city-uf-row {
  display: flex;
  gap: 0.45rem;
}
.uf-input {
  width: 75px;
  text-transform: uppercase;
}
.btn-cep {
  background: var(--primary-blue);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 0 0.85rem;
  cursor: pointer;
}
.section-hr {
  border: none;
  border-top: 1px solid #e2e8f0;
  margin: 0.5rem 0;
}
.form-actions-right {
  display: flex;
  justify-content: flex-end;
}

/* Timeline Livro */
.livro-timeline {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
}
.timeline-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-left: 4px solid var(--primary-blue);
  border-radius: 8px;
  padding: 0.75rem 1rem;
}
.tl-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.84rem;
}
.tl-date {
  font-size: 0.72rem;
  color: #64748b;
}
.tl-period {
  font-size: 0.76rem;
  color: #0369a1;
  margin-top: 0.25rem;
  font-weight: 600;
}
.tl-obs {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  color: #334155;
}

/* Tabelas e badges */
.box-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.readonly-pill {
  background: #f1f5f9;
  color: #475569;
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  font-size: 0.76rem;
  font-weight: 700;
}
.cnh-summary-bar {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.85rem 1.15rem;
  display: flex;
  gap: 2rem;
  flex-wrap: wrap;
  font-size: 0.88rem;
}
.portal-table {
  width: 100%;
  border-collapse: collapse;
}
.portal-table th {
  background: #f8fafc;
  padding: 0.7rem 0.9rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0;
}
.portal-table td {
  padding: 0.75rem 0.9rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.85rem;
}
.td-bold { font-weight: 700; color: #0f172a; }
.td-nowrap { white-space: nowrap; }

.status-pill {
  display: inline-block;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
}
.pill-ok { background: #dcfce7; color: #166534; }
.pill-warn { background: #fef3c7; color: #92400e; }
.pill-neutral { background: #e0f2fe; color: #0369a1; }

/* Arranchamento */
.dias-arranchamento-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.dia-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.dia-info {
  display: flex;
  flex-direction: column;
}
.dia-info strong { font-size: 0.88rem; color: #0f172a; }
.dia-info span { font-size: 0.76rem; color: #64748b; }
.dia-checks {
  display: flex;
  gap: 1rem;
}
.meal-check {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.84rem;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
}
.meal-yes { color: #16a34a; font-weight: 700; }
.meal-no { color: #94a3b8; }

.fo-badge-highlight {
  background: #16a34a;
  color: #ffffff;
  padding: 0.4rem 0.9rem;
  border-radius: 999px;
  font-weight: 800;
  font-size: 0.85rem;
}

.empty-mini {
  text-align: center;
  padding: 2rem 1rem;
  color: #94a3b8;
}
.empty-mini i {
  font-size: 1.75rem;
  margin-bottom: 0.4rem;
  color: #cbd5e1;
}

@media (max-width: 900px) {
  .portal-grid-2, .form-grid-3, .form-row-2 {
    grid-template-columns: 1fr;
  }
}
</style>
