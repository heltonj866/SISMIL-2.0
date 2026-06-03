<template>
  <div class="admin-page">

    <div class="page-header-banner">
      <div class="header-content">
        <div>
          <h2><i class="fas fa-cogs"></i> Administração do Sistema</h2>
          <p>Gerenciamento de usuários e permissões de acesso</p>
        </div>
        <button class="btn-add" @click="openCreate">
          <i class="fas fa-user-plus"></i> Novo Usuário
        </button>
      </div>
    </div>

    <!-- Tabela de Usuários -->
    <div class="glass-panel table-panel">
      <div class="table-top">
        <h5><i class="fas fa-users-cog"></i> Contas Cadastradas</h5>
        <span class="badge-pill">{{ usuarios.length }} conta(s)</span>
      </div>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Login (CPF / Identidade)</th>
              <th>Nível de Acesso</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in usuarios" :key="u.id">
              <td class="td-id">#{{ u.id }}</td>
              <td class="td-login"><i class="fas fa-user-circle"></i> {{ u.identidade }}</td>
              <td>
                <span class="role-badge" :class="'role-' + u.role">
                  <i :class="roleIcon(u.role)"></i> {{ roleLabel(u.role) }}
                </span>
              </td>
              <td>
                <span class="status-dot" :class="u.ativo == 1 ? 'dot-active' : 'dot-blocked'"></span>
                {{ u.ativo == 1 ? 'Ativo' : 'Bloqueado' }}
              </td>
              <td class="td-actions">
                <button class="abtn abtn-edit" @click="openEdit(u)" title="Editar">
                  <i class="fas fa-edit"></i> Editar
                </button>
                <button class="abtn abtn-del" @click="handleDelete(u)" title="Excluir">
                  <i class="fas fa-trash-alt"></i>
                </button>
              </td>
            </tr>
            <tr v-if="usuarios.length === 0">
              <td colspan="5" class="empty-row">
                <i class="fas fa-spinner fa-spin"></i> Carregando usuários...
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Criar/Editar -->
    <Transition name="fade">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box">
          <div class="modal-top">
            <h5>{{ isEditing ? 'Editar Usuário' : 'Criar Novo Usuário' }}</h5>
            <button class="modal-close" @click="showModal = false">&times;</button>
          </div>
          <form @submit.prevent="handleSave" class="modal-form">
            <div class="field">
              <label>Login (CPF / Identidade)</label>
              <input type="text" v-model="form.identidade" class="input-modern"
                :readonly="isEditing" :placeholder="isEditing ? '' : 'Ex: 123.456.789-00'" required>
              <small v-if="isEditing" class="text-muted">O login não pode ser alterado.</small>
            </div>
            <div class="field">
              <label>{{ isEditing ? 'Nova Senha (deixe vazio para manter)' : 'Senha' }}</label>
              <input type="password" v-model="form.senha" class="input-modern"
                :required="!isEditing" placeholder="••••••••">
            </div>
            <div class="field">
              <label>Nível de Acesso</label>
              <select v-model="form.role" class="input-modern">
                <option value="user">Usuário Comum (Consulta)</option>
                <option value="sargenteacao">Sargenteação / S1</option>
                <option value="s2">Inteligência / S2</option>
                <option value="enc_mat">Encarregado de Material (Rancho)</option>
                <option value="admin">Administrador</option>
              </select>
            </div>

            <div class="field">
              <label>Posto / Graduação</label>
              <select v-model="form.posto_grad" class="input-modern" required>
                <option value="">Selecione...</option>
                <option v-for="p in ['Cel','Ten Cel','Maj','Cap','1º Ten','2º Ten','Asp','Subten','1º Sgt','2º Sgt','3º Sgt','Cb','Sd EP','Sd EV','SC']" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>

            <div class="field">
              <label>Nome do Usuário</label>
              <input type="text" v-model="form.nome" class="input-modern" required>
            </div>

            <!-- Mostrar Subunidade para todos os usuários -->
            <div class="field">
              <label>Subunidade (CIA)</label>
              <select v-model="form.subunidade" class="input-modern" required>
                <option value="">Selecione a Subunidade</option>
                <option value="EM">Estado Maior</option>
                <option value="CCAP">CCAP</option>
                <option value="1CIA">1ª Cia Eng Cnst</option>
                <option value="2CIA">2ª Cia Eng Cnst</option>
                <option value="CIA_EQP">Cia Eqp E Mnt</option>
                <option value="PMGU">PMGU</option>
              </select>
            </div>

            <div class="field" v-if="isEditing">
              <label>Status da Conta</label>
              <select v-model="form.ativo" class="input-modern">
                <option value="1">Ativo</option>
                <option value="0">Bloqueado</option>
              </select>
            </div>
            <div class="modal-actions">
              <button type="button" class="abtn abtn-cancel" @click="showModal = false">Cancelar</button>
              <button type="submit" class="abtn abtn-save" :disabled="loading">
                <i class="fas fa-save"></i>
                {{ loading ? 'Salvando...' : (isEditing ? 'Salvar Alterações' : 'Criar Conta') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast, useConfirm } from '../composables/useToast'

const { success: toastSuccess, error: toastError } = useToast()
const { ask: confirmDialog } = useConfirm()

const usuarios = ref([])
const showModal = ref(false)
const loading = ref(false)
const isEditing = ref(false)
const showPassword = ref(false)
const editId = ref(null)

const form = ref({ identidade: '', senha: '', role: 'user', ativo: '1', subunidade: '', nome: '', posto_grad: '' })

const roleLabel = (r) => {
  const map = { admin: 'Administrador', sargenteacao: 'Sargenteação', s2: 'S2 / Inteligência', enc_mat: 'Enc. Material', user: 'Usuário' }
  return map[r] || r
}
const roleIcon = (r) => {
  const map = { admin: 'fas fa-shield-alt', sargenteacao: 'fas fa-star', s2: 'fas fa-car', enc_mat: 'fas fa-utensils', user: 'fas fa-user' }
  return map[r] || 'fas fa-user'
}

const fetchUsers = async () => {
  try {
    const res = await fetch('/sismil/backend/get_users.php')
    const json = await res.json()
    if (json.status === 'sucesso') usuarios.value = json.data
  } catch (e) { toastError('Erro ao carregar usuários.') }
}

const openCreate = () => {
  isEditing.value = false
  editId.value = null
  form.value = { identidade: '', senha: '', role: 'user', ativo: '1', subunidade: '', nome: '', posto_grad: '' }
  showModal.value = true
}

const openEdit = (user) => {
  isEditing.value = true
  editId.value = user.id
  form.value = {
    identidade: user.identidade,
    senha: '',
    role: user.role,
    ativo: String(user.ativo),
    subunidade: user.subunidade || '',
    nome: user.nome || '',
    posto_grad: user.posto_grad || ''
  }
  showModal.value = true
}

const handleSave = async () => {
  if (!form.value.identidade || (!isEditing.value && !form.value.senha)) {
    toastWarning("Preencha Login e Senha para novo usuário.")
    return
  }
  
  loading.value = true
  try {
    if (isEditing.value) {
      // Update
      const res = await fetch('/sismil/backend/update_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          edit_id: editId.value,
          new_user_role: form.value.role,
          new_user_pass: form.value.senha,
          ativo: form.value.ativo,
          new_user_subunidade: form.value.subunidade,
          nome: form.value.nome,
          posto_grad: form.value.posto_grad
        })
      })
      const json = await res.json()
      if (json.status === 'sucesso') {
        toastSuccess('Usuário atualizado com sucesso!')
        showModal.value = false
        fetchUsers()
      } else { toastError('Erro: ' + json.msg) }
    } else {
      // Create
      const res = await fetch('/sismil/backend/create_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          new_user_idt: form.value.identidade,
          new_user_pass: form.value.senha,
          new_user_role: form.value.role,
          new_user_subunidade: form.value.subunidade,
          nome: form.value.nome,
          posto_grad: form.value.posto_grad
        })
      })
      const json = await res.json()
      if (json.status === 'sucesso') {
        toastSuccess('Usuário criado com sucesso!')
        showModal.value = false
        form.value = { identidade: '', senha: '', role: 'user', ativo: '1', subunidade: '', nome: '', posto_grad: '' }
        fetchUsers()
      } else { toastError('Erro: ' + json.msg) }
    }
  } catch (e) { toastError('Erro de conexão com o servidor.') }
  finally { loading.value = false }
}

const handleDelete = async (u) => {
  const ok = await confirmDialog(
    `Excluir a conta "${u.identidade}" (${roleLabel(u.role)})? Essa ação não pode ser desfeita.`,
    { title: 'Excluir Usuário', confirmText: 'Excluir', variant: 'danger' }
  )
  if (!ok) return
  try {
    const fd = new FormData()
    fd.append('id_user', u.id)
    const res = await fetch('/sismil/backend/delete_user.php', { method: 'POST', body: fd })
    const json = await res.json()
    if (json.status === 'sucesso') {
      toastSuccess('Usuário excluído.')
      fetchUsers()
    } else { toastError('Erro: ' + json.msg) }
  } catch (e) { toastError('Erro de conexão.') }
}

onMounted(fetchUsers)
</script>

<style scoped>
.admin-page { padding-bottom: 2rem; }

/* Banner */
.page-header-banner {
  background: linear-gradient(120deg, #1e293b 0%, #334155 100%);
  color: white; border-radius: 16px; padding: 1.75rem 2rem;
  margin-bottom: 2rem; box-shadow: 0 6px 24px rgba(0,0,0,.2);
}
.header-content { display: flex; justify-content: space-between; align-items: center; }
.header-content h2 { margin: 0 0 .25rem; font-weight: 800; font-size: 1.4rem; }
.header-content h2 i { margin-right: .5rem; opacity: .8; }
.header-content p { margin: 0; opacity: .7; font-size: .88rem; }
.btn-add {
  background: #16a34a; color: white; border: none; border-radius: 10px;
  padding: .6rem 1.5rem; font-weight: 700; font-size: .9rem; cursor: pointer;
  display: flex; align-items: center; gap: .5rem; transition: all .2s;
}
.btn-add:hover { background: #15803d; transform: translateY(-2px); }

/* Table */
.table-panel { padding: 0; overflow: hidden; }
.table-top {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.25rem 1.5rem; border-bottom: 2px solid #f1f5f9;
}
.table-top h5 { margin: 0; font-weight: 700; color: var(--primary-blue); font-size: .95rem; }
.table-top h5 i { margin-right: .5rem; }
.badge-pill {
  background: var(--secondary-blue); color: var(--primary-blue);
  padding: .2rem .7rem; border-radius: 20px; font-size: .75rem; font-weight: 700;
}

.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th {
  background: #f8fafc; padding: .85rem 1.25rem; text-align: left;
  font-size: .78rem; font-weight: 700; color: #64748b; text-transform: uppercase;
  letter-spacing: .5px; border-bottom: 2px solid #e2e8f0;
}
.admin-table td {
  padding: .85rem 1.25rem; border-bottom: 1px solid #f1f5f9;
  font-size: .88rem; vertical-align: middle;
}
.admin-table tr:hover td { background: #f8fafc; }

.td-id { color: #94a3b8; font-weight: 600; font-size: .82rem; }
.td-login { font-weight: 700; }
.td-login i { margin-right: .4rem; color: #94a3b8; }
.empty-row { text-align: center; color: #94a3b8; padding: 2rem !important; }

/* Role badges */
.role-badge {
  display: inline-flex; align-items: center; gap: .35rem;
  padding: .3rem .7rem; border-radius: 6px; font-size: .78rem; font-weight: 700;
}
.role-badge i { font-size: .72rem; }
.role-admin       { background: #fef2f2; color: #991b1b; }
.role-sargenteacao { background: #eff6ff; color: #1e40af; }
.role-s2          { background: #ecfdf5; color: #065f46; }
.role-transporte  { background: #ecfdf5; color: #065f46; }
.role-user        { background: #f1f5f9; color: #475569; }

/* Status dot */
.status-dot {
  display: inline-block; width: 8px; height: 8px; border-radius: 50%;
  margin-right: .4rem; vertical-align: middle;
}
.dot-active  { background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,.4); }
.dot-blocked { background: #ef4444; box-shadow: 0 0 6px rgba(239,68,68,.4); }

/* Action buttons */
.td-actions { white-space: nowrap; }
.abtn {
  border: none; border-radius: 7px; padding: .35rem .75rem;
  font-size: .8rem; font-weight: 700; cursor: pointer; transition: all .2s;
  display: inline-flex; align-items: center; gap: .3rem;
}
.abtn-edit { background: #e0f2fe; color: #0369a1; margin-right: .35rem; }
.abtn-edit:hover { background: #0284c7; color: white; }
.abtn-del { background: #fee2e2; color: #991b1b; }
.abtn-del:hover { background: #dc2626; color: white; }
.abtn-cancel { background: #e5e7eb; color: #374151; }
.abtn-cancel:hover { background: #d1d5db; }
.abtn-save { background: #16a34a; color: white; }
.abtn-save:hover:not(:disabled) { background: #15803d; }
.abtn-save:disabled { opacity: .6; cursor: not-allowed; }

/* Modal */
.modal-overlay {
  position: fixed; inset: 0; z-index: 5000;
  background: rgba(0,0,0,.45); backdrop-filter: blur(3px);
  display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.modal-box {
  background: white; border-radius: 16px; width: 100%; max-width: 480px;
  overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.25);
  animation: modalPop .25s ease;
}
@keyframes modalPop { from { opacity:0; transform:scale(.93); } to { opacity:1; transform:scale(1); } }

.modal-top {
  padding: 1.15rem 1.5rem; background: var(--primary-blue); color: white;
  display: flex; justify-content: space-between; align-items: center;
}
.modal-top h5 { margin: 0; font-weight: 700; }
.modal-close {
  background: none; border: none; color: white; font-size: 1.6rem;
  cursor: pointer; opacity: .7; transition: opacity .2s;
}
.modal-close:hover { opacity: 1; }

.modal-form { padding: 1.5rem; }
.field { margin-bottom: 1.1rem; }
.field label {
  display: block; font-size: .82rem; font-weight: 600;
  color: #64748b; margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .3px;
}
.text-muted { font-size: .75rem; color: #94a3b8; display: block; margin-top: .25rem; }

.modal-actions {
  display: flex; justify-content: flex-end; gap: .65rem;
  padding-top: 1rem; border-top: 1px solid #f1f5f9; margin-top: .5rem;
}

/* Responsive */
@media (max-width: 700px) {
  .header-content { flex-direction: column; gap: 1rem; align-items: flex-start; }
  .admin-table th, .admin-table td { padding: .65rem .75rem; font-size: .82rem; }
}

/* Fade transition */
.fade-enter-active, .fade-leave-active { transition: opacity .2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
