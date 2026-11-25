<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { api, money, photo, type Studio } from '../api';
import Icon from './Icon.vue';
import Booking from './Booking.vue';
import ChatBox from './ChatBox.vue';
const props = defineProps<{ slug: string }>();
const salon = ref<Studio | null>(null),
  error = ref(''),
  chosen = ref<number | null>(null);
async function load() {
  error.value = '';
  try {
    salon.value = await api<Studio>('/salons/' + encodeURIComponent(props.slug));
  } catch (e) {
    error.value = (e as Error).message;
  }
}
function choose(id: number) {
  chosen.value = id;
  document.getElementById('rezervacija')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
onMounted(load);
</script>
<template>
  <main class="studio">
    <a class="breadcrumb" href="/pretraga"><Icon name="back" :size="16" /> Saloni</a>
    <p v-if="error" role="alert">
      {{ error }} <button class="text-btn" @click="load">Pokušaj ponovo</button>
    </p>
    <p v-else-if="!salon">Učitavamo salon…</p>
    <template v-else
      ><div
        class="studio-banner"
        :class="'tier-' + salon.plan"
        :style="{ '--salon-color': salon.color || '#365d4a' }"
      >
        <img :src="photo(salon, true)" :alt="salon.name" /><span
          v-if="salon.plan !== 'basic'"
          class="plan"
          ><Icon name="spark" :size="14" />{{
            salon.plan === 'premium' ? 'Premium' : 'Business'
          }}</span
        >
      </div>
      <div class="studio-heading">
        <img
          v-if="salon.photo && salon.cover && salon.plan !== 'basic'"
          :src="photo(salon)"
          class="avatar"
          alt="Profilna slika salona"
        />
        <div>
          <p class="eyebrow">{{ salon.category }}</p>
          <h1>{{ salon.name }}</h1>
          <p>
            <Icon name="pin" :size="16" />{{ salon.address }}, {{ salon.city }}
            <template v-if="salon.reviews_count">
              · ★ {{ Number(salon.rating).toFixed(1) }} ({{ salon.reviews_count }})</template
            >
          </p>
        </div>
      </div>
      <div class="studio-layout">
        <div>
          <section class="panel services">
            <h2>Usluge i cijene</h2>
            <div v-for="s in salon.services" :key="s.id" class="service-row">
              <div>
                <h3>{{ s.name }}</h3>
                <small>{{ s.duration }} min</small>
              </div>
              <strong>{{ money(s.price) }}</strong
              ><button class="quiet" @click="choose(s.id)">Rezerviši</button>
            </div>
            <p v-if="!salon.services.length">Salon još nije dodao usluge.</p>
          </section>
          <section class="panel">
            <h2>Ocjene klijenata</h2>
            <p v-if="!salon.reviews.length">Ovaj salon još nema recenzija.</p>
            <div v-for="(r, i) in salon.reviews" :key="i" class="review-row">
              <strong :aria-label="r.rating + ' od 5'">{{ '★'.repeat(r.rating) }}</strong>
              <p v-if="r.comment">{{ r.comment }}</p>
            </div>
          </section>
          <ChatBox v-if="salon.plan === 'premium'" :slug="slug" />
        </div>
        <Booking :salon="salon" :selected="chosen" /></div
    ></template>
  </main>
</template>
