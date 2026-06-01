import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  base: '/sismil/',
  server: {
    proxy: {
      '/sismil/backend': {
        target: 'http://localhost/sismil/backend',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/sismil\/backend/, '')
      },
      '/sismil/assets': {
        target: 'http://localhost/sismil/assets',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/sismil\/assets/, '')
      },
      '/sismil/uploads': {
        target: 'http://localhost/sismil/uploads',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/sismil\/uploads/, '')
      }
    }
  }
})
