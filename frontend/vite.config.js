import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // Адрес nginx нашего бэкенда (порт из docker/.env → NGINX_PORT)
  const backend = env.VITE_BACKEND_URL || 'http://localhost:8092'

  return {
    plugins: [vue(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: 5173,
      // Порт прописан в SANCTUM_STATEFUL_DOMAINS, поэтому не даём Vite молча взять другой
      strictPort: true,
      // В Docker на Windows изменения файлов не доходят до контейнера событиями, нужен опрос
      watch: env.VITE_USE_POLLING === 'true' ? { usePolling: true, interval: 300 } : undefined,
      // Для браузера фронт и API живут на одном адресе: CORS не нужен, cookie сессии общие
      proxy: {
        '/api': backend,
        '/sanctum': backend,
      },
    },
  }
})
