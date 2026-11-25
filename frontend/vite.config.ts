import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: Object.fromEntries(
      ['/api', '/admin', '/sanctum', '/storage', '/livewire', '/css', '/js', '/fonts/filament'].map(
        (path) => [path, 'http://127.0.0.1:8080'],
      ),
    ),
  },
});
