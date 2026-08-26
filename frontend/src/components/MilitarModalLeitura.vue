<template>
  <!-- Modal de leitura somente para usuário comum (role=user) -->
  <div class="modal-overlay" v-if="show" @click.self="$emit('close')">
    <div class="modal-content glass-panel">
      <div class="modal-header">
        <h5>Ficha do Militar</h5>
        <button class="btn-close" @click="$emit('close')">&times;</button>
      </div>
      <div class="modal-body" v-if="dados">
        <!-- Cabeçalho com foto -->
        <div class="ficha-header">
          <img :src="fotoUrl" alt="Foto" class="ficha-foto" @error="onImgError">
          <div class="ficha-title">
            <div v-if="dados.status_ativo == 0" class="badge-desligado mb-2">
              <i class="fas fa-user-slash"></i> DESLIGADO
            </div>
            <h4>{{ dados.posto_grad }} {{ dados.nome_guerra }}</h4>
            <p class="text-muted">{{ dados.nome_completo }}</p>
            <div class="badges-row">
              <span class="badge badge-primary">{{ dados.subunidade }}</span>
              <span class="badge badge-secondary" v-if="dados.pelotao">{{ dados.pelotao }}</span>
              <span class="badge badge-secondary" v-if="dados.secao">{{ dados.secao }}</span>
            </div>
          </div>
        </div>

        <!-- Dados em grid -->
        <div class="dados-grid">
          <div class="dado"><span class="dado-label">Identidade (IDT)</span><span class="dado-valor">{{ t(dados.idt_militar) }}</span></div>
          <div class="dado"><span class="dado-label">Nº</span><span class="dado-valor">{{ t(dados.numero) }}</span></div>
          <div class="dado"><span class="dado-label">QMG/QMP</span><span class="dado-valor">{{ t(dados.qmg) }}</span></div>
          <div class="dado"><span class="dado-label">Grupo Sang.</span><span class="dado-valor">{{ t(dados.tipo_sanguineo) }}</span></div>
          <div class="dado"><span class="dado-label">Nascimento</span><span class="dado-valor">{{ fmt(dados.dt_nascimento) }}</span></div>
          <div class="dado"><span class="dado-label">Data de Praça</span><span class="dado-valor">{{ fmt(dados.dt_praca) }}</span></div>
          <div class="dado"><span class="dado-label">Celular</span><span class="dado-valor">{{ t(dados.celular_princ) }}</span></div>
          <div class="dado"><span class="dado-label">Email</span><span class="dado-valor">{{ t(dados.email) }}</span></div>
          <div class="dado full-width"><span class="dado-label">Endereço</span><span class="dado-valor">{{ enderecoCompleto }}</span></div>
          <div class="dado"><span class="dado-label">Responsável (Emerg.)</span><span class="dado-valor">{{ t(dados.nome_resp) }}</span></div>
          <div class="dado"><span class="dado-label">Tel. Responsável</span><span class="dado-valor">{{ t(dados.tel_resp) }}</span></div>
        </div>

        <!-- Campos Extras: Apenas no modo Completo -->
        <template v-if="completo">
          <!-- Documentação Adicional -->
          <div class="secao-title mt-3">Documentação Adicional</div>
          <div class="dados-grid">
            <div class="dado"><span class="dado-label">CPF</span><span class="dado-valor">{{ t(dados.cpf) }}</span></div>
          </div>

          <!-- Filiação e Contato Extra -->
          <div class="secao-title mt-3">Filiação e Contato</div>
          <div class="dados-grid">
            <div class="dado full-width"><span class="dado-label">Nome do Pai</span><span class="dado-valor">{{ t(dados.nome_pai) }}</span></div>
            <div class="dado full-width"><span class="dado-label">Nome da Mãe</span><span class="dado-valor">{{ t(dados.nome_mae) }}</span></div>
            <div class="dado"><span class="dado-label">Celular Secundário</span><span class="dado-valor">{{ t(dados.celular_sec) }}</span></div>
            <div class="dado"><span class="dado-label">Telefone Fixo</span><span class="dado-valor">{{ t(dados.tel_emergencia) }}</span></div>
          </div>
        </template>

        <!-- CNH -->
        <div class="secao-title mt-3">Habilitação</div>
        <div class="dados-grid" v-if="dados.cat_cnh">
          <div class="dado"><span class="dado-label">Categoria</span><span class="dado-valor">{{ dados.cat_cnh }}</span></div>
          <div class="dado"><span class="dado-label">Validade</span><span class="dado-valor">{{ fmt(dados.validade_cnh) }}</span></div>
        </div>
        <p class="text-muted small" v-else>Não habilitado.</p>

        <!-- Veículos -->
        <div class="secao-title mt-3">Veículos</div>
        <div v-if="loadingVeiculos" class="text-muted text-center py-2">Carregando...</div>
        <div class="veiculos-grid" v-else-if="veiculos.length">
          <div v-for="v in veiculos" :key="v.id" class="veiculo-card">
            <div class="d-flex justify-between">
              <span class="placa">{{ v.placa }}</span>
              <span class="badge" :class="v.homologado == 1 ? 'badge-success' : 'badge-warning'">
                {{ v.homologado == 1 ? 'LIBERADO' : 'PENDENTE S2' }}
              </span>
            </div>
            <div class="veiculo-nome">{{ v.marca ? v.marca + ' / ' : '' }}{{ v.modelo }}</div>
            <div class="text-muted small">Cor: {{ v.cor }} | CRLV: {{ fmt(v.validade_crlv) }}</div>
          </div>
        </div>
        <p class="text-muted small" v-else>Nenhum veículo cadastrado.</p>

        <!-- Histórico: Apenas no modo Completo -->
        <template v-if="completo">
          <div class="secao-title mt-4">Histórico de Alterações</div>
          <div v-if="loadingHistorico" class="text-muted text-center py-2">Carregando histórico...</div>
          <div class="historico-container" v-else-if="historico.length">
            <div v-for="h in historico" :key="h.id" class="historico-item">
              <div class="h-data">{{ fmt(h.data_registro) }}</div>
              <div class="h-desc">{{ h.descricao }}</div>
              <div class="h-resp text-muted small">Por: {{ h.responsavel_nome }}</div>
            </div>
          </div>
          <p class="text-muted small" v-else>Nenhum histórico registrado.</p>
        </template>
      </div>
      <div class="modal-body text-center py-4" v-else>
        <i class="fas fa-spinner fa-spin fa-2x"></i>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { MilitarService } from '@/services/MilitarService'
import { VeiculoService } from '@/services/VeiculoService'

const props = defineProps({ show: Boolean, militarId: [Number, String], completo: { type: Boolean, default: false } })
defineEmits(['close'])

const dados = ref(null)
const veiculos = ref([])
const loadingVeiculos = ref(false)

const t = (v) => (v && String(v).trim() !== '') ? v : '---'
const fmt = (d) => {
  if (!d || d === '0000-00-00') return '---'
  const p = d.split('-')
  return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d
}

const fotoUrl = computed(() => {
  const p = dados.value?.foto_path
  if (!p || p === 'sem_foto.png' || p === 'sem_foto.PNG') return '/sismil/assets/sem_foto.png'
  if (p.startsWith('http') || p.startsWith('/sismil/')) return p
  return `/sismil/uploads/${p}`
})
const onImgError = (e) => { e.target.src = '/sismil/assets/sem_foto.png' }

const enderecoCompleto = computed(() => {
  if (!dados.value) return '---'
  const d = dados.value
  const parts = [d.endereco, d.num_residencia, d.bairro, d.cidade, d.estado, d.cep].filter(Boolean)
  return parts.length ? parts.join(', ') : '---'
})

const historico = ref([])
const loadingHistorico = ref(false)

const fetchDados = async (id) => {
  dados.value = null
  veiculos.value = []
  historico.value = []
  if (!id) return
  try {
    const json = await MilitarService.getById(id)
    if (json.status === 'sucesso') dados.value = json.dados

    loadingVeiculos.value = true
    const jsonV = await VeiculoService.getByMilitar(id)
    if (jsonV.status === 'sucesso') veiculos.value = jsonV.dados

    if (props.completo) {
      loadingHistorico.value = true
      const jsonH = await MilitarService.getHistorico(id)
      if (jsonH.status === 'sucesso') historico.value = jsonH.dados
    }
  } catch (e) { console.error(e) }
  finally { 
    loadingVeiculos.value = false 
    loadingHistorico.value = false
  }
}

watch(() => [props.show, props.militarId], ([show, id]) => {
  if (show && id) fetchDados(id)
})
</script>

<style scoped>
.modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); display:flex; align-items:center; justify-content:center; z-index:2000; padding:1rem; }
.modal-content { background:white; width:100%; max-width:700px; max-height:90vh; border-radius:var(--radius-lg); overflow:hidden; display:flex; flex-direction:column; }
.modal-header { padding:1rem 1.5rem; background:#f8f9fa; border-bottom:1px solid #dee2e6; display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.modal-header h5 { margin:0; font-weight:700; color:var(--primary-blue); }
.btn-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted); line-height:1; }
.modal-body { padding:1.5rem; overflow-y:auto; }

.ficha-header { display:flex; gap:1.5rem; margin-bottom:1.5rem; }
.ficha-foto { width:100px; height:125px; object-fit:cover; border-radius:8px; flex-shrink:0; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
.ficha-title h4 { font-weight:800; color:var(--primary-blue); margin:0 0 0.25rem; }
.ficha-title p { margin:0 0 0.5rem; font-size:0.9rem; }
.badges-row { display:flex; flex-wrap:wrap; gap:0.4rem; }

.badge-desligado { background:#f8d7da; color:#842029; padding:0.3rem 0.8rem; border-radius:20px; font-size:0.78rem; font-weight:700; display:inline-block; }
.badge { padding:0.3rem 0.6rem; border-radius:4px; font-size:0.75rem; font-weight:600; }
.badge-primary { background:var(--secondary-blue); color:var(--primary-blue); }
.badge-secondary { background:#e9ecef; color:#495057; }
.badge-success { background:#d1e7dd; color:#0f5132; }
.badge-warning { background:#fff3cd; color:#664d03; }

.dados-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; }
.dado { display:flex; flex-direction:column; }
.dado-label { font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; }
.dado-valor { font-size:0.9rem; font-weight:500; color:var(--text-main); }
.full-width { grid-column:1/-1; }

.secao-title { font-size:0.85rem; font-weight:700; color:var(--primary-blue); text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--secondary-blue); padding-bottom:0.25rem; margin-bottom:0.75rem; }

.veiculos-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:1rem; }
.veiculo-card { border:1px solid #dee2e6; border-radius:8px; padding:0.75rem; background:#f8f9fa; }
.placa { font-weight:800; font-family:monospace; font-size:1rem; border:2px solid #333; padding:0.1rem 0.5rem; border-radius:4px; text-transform:uppercase; background:white; }
.veiculo-nome { font-weight:700; margin-top:0.5rem; text-transform:uppercase; font-size:0.9rem; }
.d-flex { display:flex; }
.justify-between { justify-content:space-between; align-items:center; }
.mt-3 { margin-top:1rem; }
.mb-2 { margin-bottom:0.5rem; }
.mt-1 { margin-top:0.25rem; }
.text-muted { color:var(--text-muted); }
.text-danger { color:var(--danger); }
.text-center { text-align:center; }
.small { font-size:0.82rem; }
.fw-bold { font-weight:700; }
.py-2 { padding:0.5rem 0; }
.py-4 { padding:2rem 0; }
.mt-4 { margin-top:1.5rem; }

.historico-container { display:flex; flex-direction:column; gap:0.5rem; }
.historico-item { padding:0.75rem; border-left:3px solid var(--secondary-blue); background:#f8f9fa; border-radius:0 4px 4px 0; }
.h-data { font-weight:700; font-size:0.8rem; color:var(--primary-blue); margin-bottom:0.25rem; }
.h-desc { font-size:0.9rem; margin-bottom:0.25rem; line-height:1.3; }
</style>
