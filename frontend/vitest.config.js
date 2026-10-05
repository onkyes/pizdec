import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    env: { VITE_API_BASE_URL: '/api' },
    include: ['tests/**/*.test.js'],
  },
})
