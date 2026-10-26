import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const backend = { target: 'http://127.0.0.1:8000', changeOrigin: true }
export default defineConfig({
  plugins: [react()],
  server: { proxy: { '/api': backend, '/sanctum': backend } },
})
