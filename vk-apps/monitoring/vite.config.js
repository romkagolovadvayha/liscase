import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  publicDir: 'public',
  server: {
    port: 10888,
    host: true
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    copyPublicDir: true
  }
});

