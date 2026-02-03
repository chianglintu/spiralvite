import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import fs from 'node:fs'
import path from 'node:path'

function hotFilePlugin() {
  let config
  return {
    name: 'spiral-hot-file',
    configResolved(resolved) {
      config = resolved
    },
    configureServer(server) {
      const hotPath = path.resolve(config.root, 'public', 'hot')
      const url = server.resolvedUrls?.local?.[0] ?? `http://localhost:${server.config.server.port}`
      fs.writeFileSync(hotPath, url)
      server.httpServer?.once('close', () => {
        if (fs.existsSync(hotPath)) fs.unlinkSync(hotPath)
      })
    },
    buildEnd() {
      const hotPath = path.resolve(config.root, 'public', 'hot')
      if (fs.existsSync(hotPath)) fs.unlinkSync(hotPath)
    },
  }
}

export default defineConfig({
  plugins: [
    tailwindcss(),
    hotFilePlugin(),
  ],
  build: {
    outDir: 'public/build',
    manifest: true,
    assetsDir: 'assets',
    rollupOptions: {
      input: [
        'resources/js/app.js',
        'resources/css/tailwind.css',
        'resources/css/fonts.css',
      ],
    },
  },
})
