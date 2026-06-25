<template>
  <div class="militar-card glass-panel" :class="{ 'card-inativo': isDesligado }">
    <div class="card-photo">
      <img :src="fotoUrl" :alt="militar.nome_guerra" @error="onImgError">
      <span class="badge-desligado" v-if="isDesligado"><i class="fas fa-user-slash"></i> DESLIGADO</span>
    </div>
    <div class="card-info">
      <h5 class="card-nome" :class="{ 'text-danger': isDesligado }">
        {{ militar.posto_grad }} {{ militar.nome_guerra }}
      </h5>
      <p class="card-sub">{{ militar.subunidade }}</p>
      <p class="card-completo text-muted" v-if="militar.nome_completo">{{ militar.nome_completo }}</p>
      <div class="card-actions">
        <!-- admin / sargenteacao -->
        <template v-if="canEdit">
          <button class="btn-card btn-edit" @click="$emit('editar', militar)">
            <i class="fas fa-edit"></i> Editar
          </button>
          <button v-if="!isDesligado" class="btn-card btn-danger" @click="$emit('desligar', militar)">
            <i class="fas fa-user-slash"></i> Desligar
          </button>
          <button v-else class="btn-card btn-success" @click="$emit('reativar', militar)">
            <i class="fas fa-user-plus"></i> Reativar
          </button>
        </template>
        <!-- s2 / transporte -->
        <template v-else-if="canHomologar">
          <button class="btn-card btn-warning" @click="$emit('inspecionar', militar)">
            <i class="fas fa-search"></i> Inspecionar
          </button>
        </template>
        <!-- Botão Dados Pessoais para todos -->
        <button class="btn-card btn-outline" @click="$emit('resumo', militar)">
          <i class="fas fa-id-card"></i> Dados Pessoais
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

const props = defineProps({ militar: Object })
defineEmits(['editar', 'desligar', 'reativar', 'inspecionar', 'verFicha', 'resumo'])

const authStore = useAuthStore()
const canEdit = computed(() => authStore.canEdit)
const canHomologar = computed(() => authStore.canHomologar)

const isDesligado = computed(() => props.militar?.status_ativo == 0)

const fotoUrl = computed(() =>
  props.militar?.foto_path
    ? `/sismil/uploads/${props.militar.foto_path}`
    : '/sismil/assets/sem_foto.png'
)
const onImgError = (e) => { e.target.src = '/sismil/assets/sem_foto.png' }
</script>

<style scoped>
.militar-card {
  display: flex;
  flex-direction: column;
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: white;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  transition: transform 0.2s, box-shadow 0.2s;
  position: relative;
}
.militar-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
.card-inativo { opacity: 0.8; }

.card-photo {
  height: 300px;
  overflow: hidden;
  background: #e9ecef;
  position: relative;
}
.card-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 15%; }
.badge-desligado {
  position: absolute;
  bottom: 8px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(220,53,69,0.9);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
}

.card-info { padding: 1rem; display: flex; flex-direction: column; flex: 1; }
.card-nome { font-size: 0.95rem; font-weight: 700; margin: 0 0 0.25rem; color: var(--primary-blue); }
.card-sub { font-size: 0.8rem; color: var(--text-muted); margin: 0 0 0.25rem; font-weight: 600; }
.card-completo { font-size: 0.78rem; margin: 0 0 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.text-danger { color: var(--danger); }
.text-muted { color: var(--text-muted); }

.card-actions { display: flex; flex-direction: column; gap: 0.4rem; margin-top: auto; }
.btn-card {
  width: 100%; padding: 0.45rem 0.5rem; font-size: 0.82rem; font-weight: 600;
  border: none; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s;
  display: flex; align-items: center; justify-content: center; gap: 0.4rem;
}
.btn-edit { background: var(--secondary-blue); color: var(--primary-blue); border: 1px solid var(--primary-blue); }
.btn-edit:hover { background: var(--primary-blue); color: white; }
.btn-danger { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
.btn-danger:hover { background: var(--danger); color: white; }
.btn-success { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
.btn-success:hover { background: var(--success); color: white; }
.btn-warning { background: #fff3cd; color: #664d03; border: 1px solid #ffecb5; font-weight: 700; }
.btn-warning:hover { background: #ffc107; color: #000; }
.btn-info { background: #cff4fc; color: #055160; border: 1px solid #b6effb; }
.btn-info:hover { background: #0dcaf0; color: white; }
.btn-outline { background: transparent; color: var(--text-muted); border: 1px solid #dee2e6; }
.btn-outline:hover { background: #f8f9fa; }
</style>
