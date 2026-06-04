import fs from 'node:fs'
import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const port = Number(env.VITE_DEV_PORT ?? 5173)
  const host = env.VITE_DEV_HOST?.trim() || '0.0.0.0'
  const hmrHost = env.VITE_DEV_HMR_HOST?.trim() || undefined
  const certPath = env.VITE_DEV_HTTPS_CERT?.trim()
  const keyPath = env.VITE_DEV_HTTPS_KEY?.trim()
  const https =
    certPath && keyPath && fs.existsSync(certPath) && fs.existsSync(keyPath)
      ? {
          cert: fs.readFileSync(certPath),
          key: fs.readFileSync(keyPath),
        }
      : undefined
  const backendProxy = {
    target: 'http://127.0.0.1:8000',
    changeOrigin: true,
  }

  return {
    plugins: [react()],
    server: {
      host,
      port,
      https,
      hmr: hmrHost
        ? {
            host: hmrHost,
          }
        : undefined,
      proxy: {
        '/api': backendProxy,
        '/adminrepus1car': backendProxy,
      },
    },
    preview: {
      proxy: {
        '/api': backendProxy,
        '/adminrepus1car': backendProxy,
      },
    },
  }
})
