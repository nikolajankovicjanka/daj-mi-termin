import { createApp } from 'vue';

async function mount() {
  const isOwner = /^\/salon(?:\/|$)/.test(location.pathname);
  const { default: Page } = isOwner ? await import('./owner/Owner.vue') : await import('./App.vue');
  createApp(Page).mount('#app');
}
mount();
