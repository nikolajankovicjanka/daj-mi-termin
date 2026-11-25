<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, errorText, planNames, type Workspace } from './api';
import Calendar from './Calendar.vue';
import Catalog from './Catalog.vue';
import Settings from './Settings.vue';
import Inbox from './Inbox.vue';
import './owner.css';

const ws = ref<Workspace | null>(null),
  loading = ref(true),
  busy = ref(false),
  error = ref('');
const email = ref(''),
  password = ref(''),
  page = ref('Raspored');
async function load() {
  ws.value = await api<Workspace>('workspace');
}
async function init() {
  try {
    if ((await api<{ logged_in: boolean }>('session')).logged_in) await load();
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}
async function login() {
  busy.value = true;
  error.value = '';
  try {
    await api('session');
    await api('login', 'POST', { email: email.value, password: password.value });
    password.value = '';
    await load();
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
async function logout() {
  try {
    await api('logout', 'POST');
    ws.value = null;
    page.value = 'Raspored';
    error.value = '';
  } catch (e) {
    error.value = errorText(e);
  }
}
onMounted(init);
</script>
<template>
  <main class="owner-ui">
    <header class="ow-bar">
      <a href="/" class="ow-brand">Termin<span>.</span></a
      ><span class="ow-divider"></span><span>Za salone</span
      ><a class="ow-home" href="/">Javni portal</a>
    </header>
    <p v-if="loading" class="ow-wrap" role="status">Učitavanje…</p>
    <div v-else-if="!ws" class="ow-auth">
      <aside class="ow-auth-aside">
        <div class="ow-emblem">t.</div>
        <h1>Vaš salon,<br />vaš raspored.</h1>
        <p>Termini, usluge i tim na jednom mjestu.</p>
      </aside>
      <form class="ow-auth-form" @submit.prevent="login">
        <h2>Prijava vlasnika</h2>
        <label
          >Email za prijavu<input
            v-model="email"
            type="email"
            autocomplete="username"
            required /></label
        ><label
          >Lozinka<input
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
        /></label>
        <p v-if="error" role="alert" class="ow-error">{{ error }}</p>
        <button :disabled="busy">{{ busy ? 'Prijavljivanje…' : 'Prijavi se' }}</button>
      </form>
    </div>
    <div v-else class="ow-wrap">
      <header class="ow-identity">
        <div>
          <small>Radni prostor salona</small>
          <h1>{{ ws.salon.name }}</h1>
        </div>
        <span class="ow-plan">{{ planNames[ws.salon.plan] }}</span
        ><button class="ow-ghost" @click="logout">Odjava</button>
      </header>
      <nav class="ow-nav" aria-label="Radni prostor">
        <button
          v-for="item in [
            'Raspored',
            'Usluge',
            'Radnici',
            'Podešavanja',
            ...(ws.salon.plan === 'premium' ? ['Poruke'] : []),
          ]"
          :key="item"
          :class="{ active: item === page }"
          @click="page = item"
        >
          {{ item }}
        </button>
      </nav>
      <p v-if="error" class="ow-error" role="alert">{{ error }}</p>
      <Calendar v-if="page === 'Raspored'" :ws="ws" />
      <Catalog
        v-else-if="page === 'Usluge' || page === 'Radnici'"
        :key="page"
        :kind="page === 'Usluge' ? 'services' : 'employees'"
        :ws="ws"
        @saved="load"
      />
      <Settings v-else-if="page === 'Podešavanja'" :ws="ws" @saved="load" />
      <Inbox v-else />
    </div>
  </main>
</template>
