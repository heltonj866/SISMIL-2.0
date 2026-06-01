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
        <router-link to="/dashboard" class="nav-item">
          <i class="fas fa-home"></i> Início
        </router-link>
        <router-link to="/militares" class="nav-item">
          <i class="fas fa-users"></i> Militares
        </router-link>
        <!-- Frota S2: admin, s2, transporte -->
        <router-link v-if="canViewS2" to="/frota" class="nav-item">
          <i class="fas fa-car"></i> Frota S2
        </router-link>
        <router-link to="/arranchamento-painel" class="nav-item" v-if="isEncMat || isAdmin">
          <i class="fas fa-utensils"></i> Arranchamento
        </router-link>
        <!-- Administração: somente admin -->
        <router-link v-if="isAdmin" to="/admin" class="nav-item">
          <i class="fas fa-user-shield"></i> Administração
        </router-link>
      </div>

      <div class="user-info">
        <div class="role-badge">{{ authStore.userRole }}</div>
        <button class="btn-logout" @click="handleLogout">
          <i class="fas fa-sign-out-alt"></i> Sair
        </button>
      </div>
    </nav>
    <main class="main-content">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const isAdmin = computed(() => authStore.isAdmin)
const isEncMat = computed(() => authStore.isEncMat)
const canViewS2 = computed(() => authStore.canViewS2Report)

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}
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
  padding: 1.5rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.nav-item {
  display: flex;
  align-items: center;
  padding: 0.8rem 1rem;
  color: var(--text-muted);
  text-decoration: none;
  font-weight: 600;
  border-radius: var(--radius-md);
  transition: all 0.2s;
}
.nav-item i { width: 25px; font-size: 1.1rem; }
.nav-item:hover {
  background: var(--secondary-blue);
  color: var(--primary-blue);
}
.nav-item.router-link-active {
  background: var(--primary-blue);
  color: white;
}

.user-info {
  padding: 1.5rem;
  border-top: 1px solid #e9ecef;
}
.role-badge {
  display: inline-block;
  background: #e9ecef;
  color: var(--text-main);
  padding: 0.3rem 0.6rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: bold;
  text-transform: uppercase;
  margin-bottom: 1rem;
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

.main-content {
  flex: 1;
  margin-left: 250px;
  padding: 2rem;
  overflow-y: auto;
  height: 100vh;
}

@media (max-width: 768px) {
  .main-sidebar {
    width: 70px;
  }
  .sidebar-brand .brand-text, .nav-item span { display: none; }
  .user-info { padding: 1rem 0.5rem; text-align: center; }
  .role-badge { font-size: 0.6rem; padding: 0.2rem; }
  .btn-logout { font-size: 0.8rem; padding: 0.5rem 0; }
  .main-content { margin-left: 70px; padding: 1rem; }
}
</style>
