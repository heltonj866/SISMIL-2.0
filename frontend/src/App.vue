<template>
  <div class="notranslate" translate="no">
    <router-view v-slot="{ Component }">
      <transition name="fade" mode="out-in">
        <component :is="Component" />
      </transition>
    </router-view>
    <GlobalToast />
  </div>
</template>

<script setup>
import GlobalToast from './components/GlobalToast.vue'
</script>

<style>
/* Any global specific styles */
</style>
