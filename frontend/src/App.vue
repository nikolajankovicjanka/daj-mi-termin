<script setup lang="ts">
import Discovery from './components/Discovery.vue';
import StudioView from './components/StudioView.vue';
import ReviewView from './components/ReviewView.vue';
import Icon from './components/Icon.vue';
import './public.css';
const path = window.location.pathname.replace(/\/$/, '');
const segment = decodeURIComponent(path.split('/')[2] || '');
const android = import.meta.env.VITE_ANDROID_DOWNLOAD_URL;
const ios = import.meta.env.VITE_IOS_DOWNLOAD_URL;
const owner = (import.meta.env.VITE_PUBLIC_SITE_URL || '').replace(/\/$/, '') + '/salon';
</script>
<template>
  <div class="public-app">
    <header class="main-nav">
      <a class="brand" href="/" aria-label="Daj mi termin, početna"
      ><span class="brand-mark" aria-hidden="true"><i /><i /><i /><i /></span>Daj mi termin</a
      >
      <nav>
        <a href="/pretraga" class="browse-link">Istraži salone</a
        ><a class="owner-link" :href="owner"><Icon name="user" :size="17" />Za vlasnike</a>
      </nav>
    </header>
    <div class="content-width">
      <StudioView v-if="path.startsWith('/studio/') && segment" :slug="segment" /><ReviewView
        v-else-if="path.startsWith('/review/') && segment"
        :token="segment"
    />
      <main v-else><Discovery /></main>
    </div>
    <footer class="main-footer">
      <a class="brand" href="/">Daj mi termin</a>
      <nav>
        <a href="/pretraga">Pretraga salona</a><a :href="owner">Prijava za salone</a
      ><a v-if="android" :href="android">Preuzmi za Android</a
      ><a v-if="ios" :href="ios">Preuzmi za iPhone</a>
      </nav>
    </footer>
  </div>
</template>
