<template>
  <div class="glass-panel form-card">
    <div class="card-header-tabs">
      <div class="tabs" role="tablist">
        <button class="tab-btn" :class="{ active: activeTab === 'basic' }" @click="activeTab = 'basic'">Dados Básicos</button>
        <button class="tab-btn" :class="{ active: activeTab === 'complementary' }" @click="activeTab = 'complementary'">Complementares</button>
        <button class="tab-btn" :class="{ active: activeTab === 'call' }" @click="activeTab = 'call'">Endereço/Contato</button>
        <button class="tab-btn" :class="{ active: activeTab === 'vehicle' }" @click="activeTab = 'vehicle'">CNH / Veículos</button>
        <button class="tab-btn" :class="{ active: activeTab === 'photo' }" @click="activeTab = 'photo'">Foto / Arquivos</button>
        <!-- Aba S1: somente admin e sargenteacao -->
        <button v-if="canEdit" class="tab-btn" :class="{ active: activeTab === 's1' }" @click="activeTab = 's1'">Histórico S1</button>
      </div>
      <span class="mode-badge" :class="modoS2 ? 'badge-warning' : (isEdicao ? 'badge-primary' : 'badge-success')">
        {{ modoS2 ? 'Inspeção Veicular (S2)' : (isEdicao ? 'Edição de Cadastro' : 'Novo Cadastro') }}
      </span>
    </div>

    <div class="card-body">
      <form @submit.prevent="handleSave" id="militaryForm">

        <!-- Básico -->
        <div v-show="activeTab === 'basic'" class="tab-pane">
          <h5 class="section-title">Identificação Fundamental</h5>
          <div class="form-grid basic-grid">
            <div class="input-group">
              <label>CPF</label>
              <input type="text" v-model="form.cpf" class="input-modern" :readonly="isEdicao || modoS2"
                placeholder="000.000.000-00" @input="mascaraCPF" maxlength="14">
            </div>
            <div class="input-group">
              <label>Posto/Grad</label>
              <select v-model="form.posto_grad" class="input-modern" :disabled="modoS2">
                <option value="">Selecione...</option>
                <option v-for="p in postos" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="input-group">
              <label>Número</label>
              <input type="number" v-model="form.numero" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Nome de Guerra</label>
              <input type="text" v-model="form.nome_guerra" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Subunidade</label>
              <select v-model="form.subunidade" class="input-modern" :disabled="modoS2">
                <option value="Cmdo">Cmdo</option>
                <option value="EM">EM</option>
                <option value="PMGu">PMGu</option>
                <option value="Cia E Eqp Mnt">Cia E Eqp Mnt</option>
                <option value="1ª Cia E Cnst">1ª Cia E Cnst</option>
                <option value="Cia C Ap">Cia C Ap</option>
                <option value="2ª Cia E Cnst">2ª Cia E Cnst</option>
                <option value="NPOR">NPOR</option>
                <option value="PTTC">PTTC</option>
              </select>
            </div>
            <div class="input-group">
              <label>Pelotão</label>
              <input type="text" v-model="form.pelotao" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Seção</label>
              <input type="text" v-model="form.secao" class="input-modern" :disabled="isReadonly">
            </div>
          </div>
        </div>

        <!-- Complementar -->
        <div v-show="activeTab === 'complementary'" class="tab-pane">
          <h5 class="section-title">Dados Individuais</h5>
          <div class="form-grid complementary-grid">
            <div class="input-group full-width">
              <label>Nome Completo</label>
              <input type="text" v-model="form.nome_completo" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group full-width">
              <label>Nome do Pai</label>
              <input type="text" v-model="form.nome_pai" class="input-modern" :disabled="isReadonly" placeholder="Nome completo do pai">
            </div>
            <div class="input-group full-width">
              <label>Nome da Mãe</label>
              <input type="text" v-model="form.nome_mae" class="input-modern" :disabled="isReadonly" placeholder="Nome completo da mãe">
            </div>
            <div class="input-group full-width">
              <label>QMG/QMP</label>
              <select v-model="form.qmg" class="input-modern" :disabled="modoS2">
                <option value="">Selecione...</option>
                <option value="Eng">Eng</option>
                <option value="Int">Int</option>
                <option value="QCO">QCO</option>
                <option value="QEM">QEM</option>
                <option value="Sau">Sau</option>
                <option value="Com">Com</option>
                <option value="QAO">QAO</option>
                <option value="OTT">OTT</option>
                <option value="Topo">Topo</option>
                <option value="Cav">Cav</option>
                <option value="MB">MB</option>
                <option value="QE">QE</option>
                <option value="STT">STT</option>
                <option value="PCCTM">PCCTM</option>
                <option value="PPGPE">PPGPE</option>
                <option value="09-45">09-45</option>
                <option value="05-01">05-01</option>
                <option value="09-51">09-51</option>
                <option value="05-23">05-23</option>
                <option value="09-47">09-47</option>
                <option value="10-55">10-55</option>
                <option value="05-22">05-22</option>
                <option value="00-10">00-10</option>
                <option value="08-33">08-33</option>
                <option value="09-50">09-50</option>
                <option value="11-73">11-73</option>
                <option value="10-42">10-42</option>
                <option value="10-61">10-61</option>
                <option value="05-15">05-15</option>
                <option value="11-74">11-74</option>
                <option value="07-01">07-01</option>
              </select>
            </div>
            <div class="input-group">
              <label>Nascimento</label>
              <input type="date" v-model="form.dt_nascimento" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Grupo Sang.</label>
              <select v-model="form.tipo_sanguineo" class="input-modern" :disabled="modoS2">
                <option value="A+">A+</option><option value="A-">A-</option>
                <option value="B+">B+</option><option value="B-">B-</option>
                <option value="AB+">AB+</option><option value="AB-">AB-</option>
                <option value="O+">O+</option><option value="O-">O-</option>
              </select>
            </div>
            <div class="input-group">
              <label>Data de Praça</label>
              <input type="date" v-model="form.dt_praca" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Idt Militar</label>
              <input type="text" v-model="form.idt_militar" class="input-modern" :disabled="isReadonly">
            </div>
          </div>
        </div>

        <!-- Contato e Endereço -->
        <div v-show="activeTab === 'call'" class="tab-pane">
          <h5 class="section-title">Contato e Emergência</h5>
          <div class="form-grid basic-grid mb-4">
            <div class="input-group full-width">
              <label>E-mail</label>
              <input type="email" v-model="form.email" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Celular (WhatsApp)</label>
              <input type="text" v-model="form.celular_princ" class="input-modern" :disabled="isReadonly"
                placeholder="(00) 00000-0000" @input="mascaraTel($event, 'celular_princ')" maxlength="16">
            </div>
            <div class="input-group">
              <label>Celular Secundário</label>
              <input type="text" v-model="form.celular_sec" class="input-modern" :disabled="isReadonly"
                placeholder="(00) 00000-0000" @input="mascaraTel($event, 'celular_sec')" maxlength="16">
            </div>
            <div class="input-group full-width">
              <label>Nome (Contato Emergência)</label>
              <input type="text" v-model="form.nome_resp" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Telefone (Emergência)</label>
              <input type="text" v-model="form.tel_resp" class="input-modern" :disabled="isReadonly"
                placeholder="(00) 00000-0000" @input="mascaraTel($event, 'tel_resp')" maxlength="16">
            </div>
            <div class="input-group">
              <label>Telefone Alternativo (Emergência)</label>
              <input type="text" v-model="form.tel_emergencia" class="input-modern" :disabled="isReadonly"
                placeholder="(00) 00000-0000" @input="mascaraTel($event, 'tel_emergencia')" maxlength="16">
            </div>
          </div>
          <h5 class="section-title">Endereço Residencial</h5>
          <div class="form-grid basic-grid">
            <div class="input-group">
              <label>CEP</label>
              <input type="text" v-model="form.cep" class="input-modern" :disabled="isReadonly"
                placeholder="00000-000" @input="onCepInput" maxlength="9">
            </div>
            <div class="input-group" style="grid-column: span 3;">
              <label>Logradouro</label>
              <input type="text" v-model="form.endereco" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Número</label>
              <input type="text" v-model="form.num_residencia" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Bairro</label>
              <input type="text" v-model="form.bairro" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>Cidade</label>
              <input type="text" v-model="form.cidade" class="input-modern" :disabled="isReadonly">
            </div>
            <div class="input-group">
              <label>UF</label>
              <input type="text" v-model="form.estado" class="input-modern" :disabled="isReadonly" maxlength="2">
            </div>
          </div>
        </div>

        <!-- CNH e Veículo -->
        <div v-show="activeTab === 'vehicle'" class="tab-pane">
          <h5 class="section-title">Habilitação / CNH</h5>
          <div class="form-grid basic-grid">
            <div class="input-group">
              <label>Categoria CNH</label>
              <select v-model="form.cat_cnh" class="input-modern" :disabled="modoS2">
                <option value="">Nenhuma</option>
                <option value="A">A (Moto)</option>
                <option value="B">B (Carro)</option>
                <option value="AB">AB (Moto/Carro)</option>
                <option value="C">C (Caminhão)</option>
                <option value="D">D (Ônibus)</option>
                <option value="E">E (Carreta)</option>
                <option value="AD">AD</option>
                <option value="AE">AE</option>
              </select>
            </div>
            <div class="input-group">
              <label>Validade CNH</label>
              <input type="date" v-model="form.validade_cnh" class="input-modern" :disabled="isReadonly">
            </div>
            <!-- CNH PDF: upload para admin/sargenteacao, visualização para S2 -->
            <div class="input-group full-width">
              <label>CNH Digital (PDF)
                <span v-if="!modoS2" class="label-hint">— Anexe a CNH digitalizada do militar</span>
              </label>
              <!-- Link para visualizar CNH (TODOS os roles quando existe) -->
              <div v-if="dadosCompletos?.pdf_habilitacao" class="cnh-link-box">
                <a :href="`/sismil/uploads/documentos/${dadosCompletos.pdf_habilitacao}`" target="_blank" class="doc-link-big">
                  <i class="fas fa-file-pdf fa-lg"></i>
                  <span>Visualizar CNH Anexada</span>
                </a>
                <span class="text-muted small ml-2">Clique para abrir o PDF</span>
              </div>
              <!-- Se não tem CNH -->
              <div v-else class="cnh-empty-box">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <span>{{ modoS2 ? 'Nenhuma CNH digitalizada foi anexada ainda.' : 'Nenhuma CNH anexada. Selecione o arquivo abaixo.' }}</span>
              </div>
              <!-- Upload (somente admin/sargenteacao) -->
              <div v-if="!modoS2" class="cnh-upload-box">
                <label class="upload-label">
                  <input type="file" @change="handleFileChange($event, 'pdf_habilitacao')" accept="application/pdf" class="input-file-hidden">
                  <span class="upload-btn">
                    <i class="fas fa-upload"></i>
                    {{ fileUploads.pdf_habilitacao ? fileUploads.pdf_habilitacao.name : (dadosCompletos?.pdf_habilitacao ? 'Substituir CNH (PDF)' : 'Selecionar CNH (PDF)') }}
                  </span>
                </label>
              </div>
            </div>
          </div>
          <hr style="margin: 1.5rem 0; border: none; border-top: 2px solid #e9ecef;">
          <!-- Lista de veículos com permissões por role -->
          <VehicleList :militarId="dadosCompletos?.id || militar?.id" :dadosMilitar="dadosCompletos" />
        </div>

        <!-- Foto e Arquivos (somente admin/sargenteacao) -->
        <div v-show="activeTab === 'photo'" class="tab-pane">
          <h5 class="section-title">Foto e Documentos</h5>
          <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
            <!-- Área da Foto -->
            <div style="display: flex; flex-direction: column; gap: 1rem; flex: 1; min-width: 250px; background: #f8fbff; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0;">
              <h6 style="margin: 0; color: var(--primary-blue); font-weight: 700;"><i class="fas fa-camera"></i> Foto de Perfil</h6>
              
              <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
                <!-- Prévia temporária -->
                <div v-if="fotoPreviewUrl" class="foto-preview-area">
                  <img :src="fotoPreviewUrl" alt="Prévia da Foto" class="foto-atual">
                  <span class="badge" style="background-color: #fef3c7; color: #d97706; font-size: 0.7rem; font-weight: 700; padding: 0.25rem 0.5rem; border-radius: 4px;">Nova foto selecionada</span>
                </div>
                <!-- Foto atual do banco -->
                <div v-else-if="fotoAtual" class="foto-preview-area">
                  <img :src="`/sismil/uploads/${fotoAtual}`" alt="Foto Atual" class="foto-atual" @error="onImgError">
                  <span class="text-muted small">Foto atual</span>
                </div>
                <!-- Sem foto -->
                <div v-else class="foto-preview-area" style="width: 95px; height: 120px; background: #e2e8f0; display:flex; align-items:center; justify-content:center; border-radius:6px; flex-direction: column; gap: 0.5rem;">
                  <i class="fas fa-user text-muted" style="font-size: 2rem;"></i>
                  <span class="text-muted small">Sem foto</span>
                </div>
                
                <div style="flex: 1; min-width: 200px;">
                  <label style="margin-bottom: 0.5rem; display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Anexar nova foto (JPG/PNG)</label>
                  <input type="file" @change="handleFileChange($event, 'foto')" accept="image/*" class="input-modern" :disabled="modoS2">
                  
                  <!-- Painel de Instruções -->
                  <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 0.75rem; color: #166534;">
                    <h6 style="margin: 0 0 0.4rem 0; font-weight: 700; color: #15803d; font-size: 0.8rem; display: flex; align-items: center; gap: 0.25rem;"><i class="fas fa-info-circle"></i> Instruções para a Foto:</h6>
                    <ul style="margin: 0; padding-left: 1.15rem; line-height: 1.4; display: flex; flex-direction: column; gap: 0.2rem;">
                      <li><strong>Fundo:</strong> Neutro (preferencialmente branco ou azul claro).</li>
                      <li><strong>Uniforme:</strong> Militar fardado (de acordo com as normas, sem cobertura/boina).</li>
                      <li><strong>Qualidade:</strong> Rosto centralizado, boa iluminação, foco nítido e de frente.</li>
                      <li><strong>Formatos aceitos:</strong> JPG, JPEG ou PNG.</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <!-- Área de Arquivos -->
            <div style="display: flex; flex-direction: column; gap: 1rem; flex: 1; min-width: 250px; background: #fffbf1; padding: 1.5rem; border-radius: 10px; border: 1px solid #fde68a;">
              <h6 style="margin: 0; color: #92400e; font-weight: 700;"><i class="fas fa-file-pdf"></i> Documentação Administrativa</h6>
              
              <div>
                <label style="margin-bottom: 0.5rem; display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Ficha de Nada Consta (Desligamento - PDF)</label>
                <input type="file" @change="handleFileChange($event, 'nada_consta')" accept="application/pdf" class="input-modern" :disabled="modoS2">
              </div>
              
              <div v-if="dadosCompletos?.pdf_nada_consta" class="doc-link-area" style="margin-top: 0.5rem;">
                <a :href="`/sismil/uploads/documentos/${dadosCompletos.pdf_nada_consta}`" target="_blank" class="doc-link">
                  <i class="fas fa-file-pdf"></i> Visualizar Ficha Anexada
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Histórico S1 (somente admin/sargenteacao) -->
        <div v-if="canEdit" v-show="activeTab === 's1'" class="tab-pane">
          <S1History :militarId="militar?.id" />
        </div>

        <!-- Rodapé do formulário -->
        <div class="form-footer">
          <div class="footer-left">
            <!-- Botão Gerar Dossier PDF (admin/sargenteacao quando editando) -->
            <a v-if="canEdit && isEdicao" :href="`/sismil/backend/dossier_militar.php?id=${militar.id}`"
              target="_blank" class="btn-modern btn-secondary-outline me-2">
              <i class="fas fa-file-contract"></i> Gerar Dossier (PDF)
            </a>
            <!-- Botão Excluir Permanente (somente admin) -->
            <button v-if="canDelete && isEdicao" type="button" class="btn-modern btn-danger-outline"
              @click="handleExcluirPermanente">
              <i class="fas fa-trash-alt"></i> Excluir Cadastro
            </button>
          </div>
          <div class="footer-right">
            <button type="button" class="btn-modern btn-secondary-outline me-2" @click="$emit('cancel')">
              {{ modoS2 ? 'Fechar Ficha' : 'Cancelar' }}
            </button>
            <!-- Salvar Dados: somente admin e sargenteacao -->
            <button v-if="canEdit && !readonlyAll" type="submit" class="btn-modern btn-success" :disabled="loading">
              <i class="fas fa-save"></i> {{ loading ? 'Salvando...' : 'Salvar Dados' }}
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useToast, useConfirm } from '../composables/useToast'
import VehicleList from './VehicleList.vue'
import S1History from './S1History.vue'
import { MilitarService } from '@/services/MilitarService'

const { success: toastSuccess, error: toastError, warning: toastWarning } = useToast()
const { ask: confirmDialog } = useConfirm()

const props = defineProps({
  militar: {
    type: Object,
    default: null
  },
  modoS2: {
    type: Boolean,
    default: false
  },
  readonlyAll: {
    type: Boolean,
    default: false
  }
})

const isReadonly = computed(() => props.readonlyAll || props.modoS2)
const emit = defineEmits(['cancel', 'saved'])

const authStore = useAuthStore()
const canEdit = computed(() => authStore.canEdit)
const canDelete = computed(() => authStore.canDelete)

const isEdicao = computed(() => !!props.militar?.id)
const loading = ref(false)
const dadosCompletos = ref(null) // dados buscados do backend

const fotoAtual = computed(() => dadosCompletos.value?.foto_path || props.militar?.foto_path || null)

// Busca dados completos quando o militar tem ID
const fetchDadosCompletos = async () => {
  // Limpa prévias e arquivos anteriores
  if (fotoPreviewUrl.value) {
    URL.revokeObjectURL(fotoPreviewUrl.value)
  }
  fotoPreviewUrl.value = null
  fileUploads.value = { foto: null, nada_consta: null, pdf_habilitacao: null }

  const id = props.militar?.id
  if (!id) { dadosCompletos.value = null; return }
  try {
    const json = await MilitarService.getById(id)
    if (json.status === 'sucesso') {
      dadosCompletos.value = json.dados
      // Preenche o form com dados completos
      const d = json.dados
      form.value.id = d.id || ''
      form.value.id_militar = d.id || ''
      form.value.cpf = d.cpf || d.identidade || ''
      form.value.posto_grad = d.posto_grad || ''
      form.value.numero = d.numero || ''
      form.value.nome_guerra = d.nome_guerra || ''
      form.value.subunidade = d.subunidade || ''
      form.value.pelotao = d.pelotao || ''
      form.value.secao = d.secao || ''
      form.value.nome_completo = d.nome_completo || ''
      form.value.nome_pai = d.nome_pai || ''
      form.value.nome_mae = d.nome_mae || ''
      form.value.qmg = d.qmg || ''
      form.value.dt_nascimento = d.dt_nascimento || ''
      form.value.tipo_sanguineo = d.tipo_sanguineo || ''
      form.value.dt_praca = d.dt_praca || ''
      form.value.idt_militar = d.idt_militar || ''
      form.value.email = d.email || ''
      form.value.celular_princ = d.celular_princ || ''
      form.value.celular_sec = d.celular_sec || ''
      form.value.nome_resp = d.nome_resp || ''
      form.value.tel_resp = d.tel_resp || ''
      form.value.tel_emergencia = d.tel_emergencia || ''
      form.value.cep = d.cep || ''
      form.value.endereco = d.endereco || ''
      form.value.num_residencia = d.num_residencia || ''
      form.value.bairro = d.bairro || ''
      form.value.cidade = d.cidade || ''
      form.value.estado = d.estado || ''
      form.value.cat_cnh = d.cat_cnh || ''
      form.value.validade_cnh = d.validade_cnh || ''
    }
  } catch (e) { console.error('Erro ao buscar dados do militar:', e) }
}

onMounted(fetchDadosCompletos)
watch(() => props.militar?.id, fetchDadosCompletos)

// Se s2, abrir direto na aba de veículo
const activeTab = ref(props.modoS2 ? 'vehicle' : 'basic')

watch(() => props.modoS2, (val) => {
  if (val) activeTab.value = 'vehicle'
})

const postos = ["Cel", "TC", "Maj", "Cap", "1º Ten", "2º Ten", "Asp", "S Ten", "1º Sgt", "2º Sgt", "3º Sgt", "Alu", "Cb", "Sd EP", "Sd EV", "SC"]

const form = ref({
  id: props.militar?.id || '',
  id_militar: props.militar?.id || '',
  cpf: props.militar?.cpf || props.militar?.identidade || '',
  posto_grad: props.militar?.posto_grad || '',
  numero: props.militar?.numero || '',
  nome_guerra: props.militar?.nome_guerra || '',
  subunidade: props.militar?.subunidade || '',
  pelotao: props.militar?.pelotao || '',
  secao: props.militar?.secao || '',
  nome_completo: props.militar?.nome_completo || '',
  nome_pai: props.militar?.nome_pai || '',
  nome_mae: props.militar?.nome_mae || '',
  qmg: props.militar?.qmg || '',
  dt_nascimento: props.militar?.dt_nascimento || '',
  tipo_sanguineo: props.militar?.tipo_sanguineo || '',
  dt_praca: props.militar?.dt_praca || '',
  idt_militar: props.militar?.idt_militar || '',
  email: props.militar?.email || '',
  celular_princ: props.militar?.celular_princ || '',
  celular_sec: props.militar?.celular_sec || '',
  nome_resp: props.militar?.nome_resp || '',
  tel_resp: props.militar?.tel_resp || '',
  tel_emergencia: props.militar?.tel_emergencia || '',
  cep: props.militar?.cep || '',
  endereco: props.militar?.endereco || '',
  num_residencia: props.militar?.num_residencia || '',
  bairro: props.militar?.bairro || '',
  cidade: props.militar?.cidade || '',
  estado: props.militar?.estado || '',
  cat_cnh: props.militar?.cat_cnh || '',
  validade_cnh: props.militar?.validade_cnh || '',
})

const fileUploads = ref({ foto: null, nada_consta: null, pdf_habilitacao: null })
const fotoPreviewUrl = ref(null)

const handleFileChange = (e, type) => {
  const file = e.target.files.length > 0 ? e.target.files[0] : null
  fileUploads.value[type] = file
  
  if (type === 'foto') {
    if (fotoPreviewUrl.value) {
      URL.revokeObjectURL(fotoPreviewUrl.value)
    }
    if (file) {
      fotoPreviewUrl.value = URL.createObjectURL(file)
    } else {
      fotoPreviewUrl.value = null
    }
  }
}
const onImgError = (e) => { e.target.src = '/sismil/assets/sem_foto.png' }

const mascaraCPF = (e) => {
  let v = e.target.value.replace(/\D/g, '').substring(0, 11)
  v = v.replace(/(\d{3})(\d)/, '$1.$2')
  v = v.replace(/(\d{3})(\d)/, '$1.$2')
  v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2')
  form.value.cpf = v
}

const mascaraTel = (e, field) => {
  let v = e.target.value.replace(/\D/g, '').substring(0, 11)
  v = v.replace(/^(\d{2})(\d)/g, '($1) $2')
  v = v.replace(/(\d)(\d{4})$/, '$1-$2')
  form.value[field] = v
}

const onCepInput = async (e) => {
  let v = e.target.value.replace(/\D/g, '').substring(0, 8)
  form.value.cep = v.replace(/^(\d{5})(\d)/, '$1-$2')
  
  if (v.length === 8) {
    try {
      let json = null
      try {
        const res = await apiFetch(`/sismil/backend/api/cep?cep=${v}`)
        const data = await res.json()
        if (data.status === 'sucesso' && data.dados) {
          json = data.dados
        }
      } catch (err) {}

      if (!json) {
        const res = await fetch(`https://viacep.com.br/ws/${v}/json/`)
        json = await res.json()
      }

      if (json && !json.erro) {
        form.value.endereco = json.logradouro || form.value.endereco
        form.value.bairro = json.bairro || form.value.bairro
        form.value.cidade = json.localidade || form.value.cidade
        form.value.estado = json.uf || form.value.estado
      }
    } catch (err) { console.error('Erro na consulta do CEP:', err) }
  }
}

const handleSave = async () => {
  if (!canEdit.value) return
  if (!form.value.cpf || !form.value.posto_grad || !form.value.nome_guerra) {
    toastWarning('Preencha os dados básicos obrigatórios: CPF, Posto/Grad e Nome de Guerra.')
    return
  }
  loading.value = true
  const fd = new FormData()
  for (const key in form.value) fd.append(key, form.value[key])
  if (fileUploads.value.foto) fd.append('foto', fileUploads.value.foto)
  if (fileUploads.value.nada_consta) fd.append('pdf_nada_consta', fileUploads.value.nada_consta)
  if (fileUploads.value.pdf_habilitacao) fd.append('pdf_habilitacao', fileUploads.value.pdf_habilitacao)
  try {
    const json = await MilitarService.save(fd)
    if (json.status === 'sucesso' || json.id) emit('saved')
    else toastError('Erro: ' + (json.msg || 'Erro ao salvar.'))
  } catch (e) { toastError('Erro de conexão ao salvar.') }
  finally { loading.value = false }
}

const handleExcluirPermanente = async () => {
  const ok = await confirmDialog('ATENÇÃO: Isso apagará permanentemente o militar, seu histórico e toda a frota! Use apenas se o cadastro foi um erro.', { title: 'Confirmação', variant: 'danger' })
  if (!ok) return
  try {
    const json = await MilitarService.delete({ id: props.militar.id })
    if (json.status === 'sucesso') emit('saved')
    else toastError('Erro ao excluir: ' + (json.msg || 'Erro desconhecido'))
  } catch (e) { toastError('Erro de conexão ao excluir.') }
}
</script>

<style scoped>
.form-card { margin-bottom: 2rem; background: white; border-radius: var(--radius-lg); }
.card-header-tabs { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #dee2e6; padding: 0; background: #f8f9fa; border-radius: var(--radius-lg) var(--radius-lg) 0 0; overflow-x: auto; }
.tabs { display: flex; flex-shrink: 0; }
.tab-btn { background: transparent; border: none; padding: 1rem 1.25rem; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.2s; white-space: nowrap; }
.tab-btn:hover { background: rgba(0,0,0,0.04); }
.tab-btn.active { color: var(--primary-blue); border-bottom-color: var(--primary-blue); background: white; }

.mode-badge { padding: 0.4rem 0.9rem; border-radius: 6px; margin-right: 1rem; font-size: 0.8rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
.badge-primary { background: var(--primary-blue); color: white; }
.badge-success { background: var(--success); color: white; }
.badge-warning { background: #ffc107; color: #212529; }

.card-body { padding: 2rem; }
.section-title { font-size: 1.1rem; color: var(--primary-blue); margin-bottom: 1.5rem; border-bottom: 2px solid #e9ecef; padding-bottom: 0.5rem; font-weight: 700; }
.form-grid { display: grid; gap: 1rem; }
.basic-grid { grid-template-columns: repeat(4, 1fr); }
.complementary-grid { grid-template-columns: repeat(4, 1fr); }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.4rem; }
.full-width { grid-column: span 2; }
.mb-4 { margin-bottom: 1.5rem; }
.me-2 { margin-right: 0.5rem; }
.text-muted { color: var(--text-muted); }
.small { font-size: 0.82rem; }

.form-footer { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.footer-left { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.footer-right { display: flex; align-items: center; gap: 0.5rem; }
.btn-danger-outline { border: 1px solid var(--danger); color: var(--danger); background: transparent; }
.btn-danger-outline:hover { background: var(--danger); color: white; }
.btn-secondary-outline { border: 1px solid #adb5bd; color: #6c757d; background: transparent; }
.btn-secondary-outline:hover { background: #6c757d; color: white; }

.foto-preview-area { display: flex; flex-direction: column; align-items: flex-start; gap: 0.5rem; }
.foto-atual { height: 120px; width: 95px; object-fit: cover; border-radius: 6px; border: 2px solid #dee2e6; }

@media (max-width: 900px) {
  .basic-grid, .complementary-grid { grid-template-columns: repeat(2, 1fr); }
  .full-width { grid-column: span 2; }
  .input-group[style*="grid-column"] { grid-column: span 2 !important; }
}
@media (max-width: 600px) {
  .basic-grid, .complementary-grid { grid-template-columns: 1fr; }
  .full-width { grid-column: span 1; }
  .input-group[style*="grid-column"] { grid-column: span 1 !important; }
}

.doc-link-area { margin-top: 0.5rem; }
.doc-link {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: #f8d7da; color: #842029; border: 1px solid #f5c2c7;
  padding: 0.35rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;
  text-decoration: none; transition: all 0.2s;
}
.doc-link:hover { background: var(--danger); color: white; }

/* CNH Section */
.label-hint { font-size: 0.75rem; color: var(--text-muted); font-weight: 400; margin-left: 0.25rem; }
.ml-2 { margin-left: 0.5rem; }

.cnh-link-box {
  display: flex; align-items: center; gap: 1rem; margin: 0.5rem 0;
  padding: 0.75rem 1rem; background: #d1e7dd; border: 1px solid #badbcc;
  border-radius: 8px;
}
.doc-link-big {
  display: inline-flex; align-items: center; gap: 0.6rem;
  background: var(--danger); color: white;
  padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.9rem; font-weight: 700;
  text-decoration: none; transition: all 0.2s;
}
.doc-link-big:hover { background: #bb2d3b; transform: translateY(-1px); }

.cnh-empty-box {
  display: flex; align-items: center; gap: 0.75rem; margin: 0.5rem 0;
  padding: 0.75rem 1rem; background: #fff3cd; border: 1px solid #ffe69c;
  border-radius: 8px; font-size: 0.88rem; color: #664d03;
}
.text-warning { color: #856404; }

.cnh-upload-box { margin-top: 0.75rem; }
.upload-label { cursor: pointer; display: block; }
.input-file-hidden { display: none; }
.upload-btn {
  display: inline-flex; align-items: center; gap: 0.5rem;
  background: var(--primary-blue); color: white;
  padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.88rem; font-weight: 600;
  cursor: pointer; transition: all 0.2s;
}
.upload-btn:hover { background: #063a6e; }
</style>
