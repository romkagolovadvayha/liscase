import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
  plugins: [
    react(),
    viteStaticCopy({
      targets: [
        {
          src: 'widget.html',
          dest: '.'
        }
      ]
    })
  ],
  publicDir: 'public',
  server: {
    port: 10888,
    host: true
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    copyPublicDir: true,
    rollupOptions: {
      input: {
        main: './index.html',
        widget: './widget.html'
      }
    }
  }
});

