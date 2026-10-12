import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Proxy de dev : /api/* → API PHP sous XAMPP.
// Le `rewrite` évite de dupliquer le préfixe "/api" dans la cible.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost/ssmunie/api',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
      '/uploads': {
        target: 'http://localhost/ssmunie/uploads',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/uploads/, ''),
      },
    },
  },
});
