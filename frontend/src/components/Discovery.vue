<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, categories, today, type Salon } from '../api';
import { getLocation } from '../platform';
import SalonSearch from './SalonSearch.vue';
import Icon from './Icon.vue';
import SalonCard from './SalonCard.vue';
import SalonMap from './SalonMap.vue';
const params = new URLSearchParams(location.search);
const searching = ref(location.pathname === '/pretraga'),
    category = ref(params.get('category') || '');
const query = ref(params.get('q') || ''),
    city = ref(params.get('city') || ''),
    sort = ref(params.get('sort') || 'recommended');
const date = ref(params.get('date') || ''),
    time = ref(params.get('time') || '');
const locationPoint = ref<{ lat: number; lng: number } | null>(null),
    geoInfo = ref(''),
    locating = ref(false);
const list = ref<Salon[]>([]),
    loading = ref(true),
    error = ref(''),
    showMap = ref(true);
const premium = computed(() => list.value.filter((s) => s.plan === 'premium'));
const top = computed(() =>
    list.value
        .filter((s) => s.reviews_count > 0 && s.rating != null)
        .sort((a, b) => Number(b.rating) - Number(a.rating) || b.reviews_count - a.reviews_count)
        .slice(0, 6),
);
const categoryIcons = ['barber', 'hair', 'scissors', 'physio', 'foot', 'nail', 'eye', 'spine'];
const form = ref<InstanceType<typeof SalonSearch>>();
let geoRequest = 0;
let request = 0;
function filters() {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries({
    category: category.value,
    city: city.value,
    q: query.value,
    sort: sort.value,
    date: date.value,
    time: date.value ? time.value : '',
  }))
    if (v) p.set(k, v.trim());
  return p;
}
async function fetchResults() {
  const id = ++request,
      p = searching.value ? filters() : new URLSearchParams();
  if (searching.value && locationPoint.value) {
    p.set('lat', String(locationPoint.value.lat));
    p.set('lng', String(locationPoint.value.lng));
  }
  loading.value = true;
  error.value = '';
  try {
    const data = await api<Salon[]>('/salons?' + p);
    if (id === request) list.value = data;
  } catch {
    if (id === request) {
      list.value = [];
      error.value = 'Saloni se trenutno ne mogu učitati.';
    }
  } finally {
    if (id === request) loading.value = false;
  }
}
function search() {
  searching.value = true;
  history.replaceState({}, '', '/pretraga?' + filters());
  fetchResults();
}
async function locate() {
  if (locating.value) return;
  locating.value = true;
  const id = ++geoRequest;
  try {
    const p = await getLocation();
    if (id !== geoRequest) return;
    locationPoint.value = { lat: p.latitude, lng: p.longitude };
    city.value = '';
    geoInfo.value = 'Prikazujemo salone u tvojoj blizini.';
    search();
  } catch {
    if (id !== geoRequest) return;
    geoInfo.value = 'Lokacija nije dostupna. Upiši grad za pretragu.';
    form.value?.focusCity();
  } finally {
    locating.value = false;
  }
}
function setCategory(c: string) {
  category.value = c;
  showMap.value = true;
  search();
  window.scrollTo({ top: 0 });
}
function setCity() {
  geoRequest++;
  locationPoint.value = null;
  geoInfo.value = '';
}
onMounted(() => {
  fetchResults();
});
</script>
<template>
  <div :class="searching ? 'results-bar' : 'discovery-hero'">
    <div v-if="!searching" class="intro">
      <h1>Tvoj sljedeći<br />termin je <em>blizu.</em></h1>
      <p>Pronađi salon ili studio u svom gradu i izaberi vrijeme koje ti odgovara.</p>
    </div>
    <SalonSearch
        ref="form"
        v-model:query="query"
        v-model:city="city"
        :locating="locating"
        :message="geoInfo"
        @city-input="setCity"
        @search="search"
        @locate="locate"
    />
    <img
        v-if="!searching"
        class="intro-photo"
        src="/images/salon-2.jpg"
        alt="Frizerka oblikuje kosu klijentkinji u salonu"
        fetchpriority="high"
    />
  </div>
  <section class="categories" :class="{ compact: searching }">
    <h2 v-if="!searching">Istraži kategorije</h2>
    <div class="category-strip">
      <button
          v-for="(c, i) in categories"
          :key="c"
          :aria-pressed="category === c"
          @click="setCategory(c)"
      >
        <span class="category-icon"><Icon :name="categoryIcons[i]" :size="26" /></span>{{ c }}
      </button>
    </div>
  </section>
  <template v-if="searching">
    <section class="result-heading">
      <div>
        <a href="/" class="breadcrumb">Početna</a>
        <h1>{{ category || 'Svi saloni' }}</h1>
        <p v-if="!loading && !error">Broj rezultata · {{ list.length }}</p>
      </div>
      <button class="quiet" :aria-pressed="showMap" @click="showMap = !showMap">
        <Icon name="map" />{{ showMap ? 'Sakrij mapu' : 'Prikaži mapu' }}
      </button>
    </section>
    <form class="filters" @submit.prevent="search">
      <label
      >Sortiranje<select v-model="sort" @change="search">
        <option value="recommended">Preporučeni</option>
        <option value="rating">Najbolje ocijenjeni</option>
        <option value="price_asc">Najjeftiniji</option>
        <option value="price_desc">Najskuplji</option>
        <option value="popular">Najviše rezervacija</option>
      </select></label
      >
      <label
      >Slobodan datum<input type="date" :min="today()" v-model="date" @change="search" /></label
      ><label>Vrijeme<input type="time" v-model="time" :disabled="!date" @change="search" /></label>
      <button
          v-if="category || city || query || date || sort !== 'recommended' || locationPoint"
          type="button"
          class="text-btn"
          @click="
          category = '';
          city = '';
          query = '';
          date = '';
          time = '';
          sort = 'recommended';
          setCity();
          search();
        "
      >
        Očisti filtere
      </button>
    </form>
    <div :class="['results', { mapped: showMap }]">
      <div>
        <p v-if="loading" class="status" role="status">Učitavamo salone…</p>
        <p v-else-if="error" class="status" role="alert">
          {{ error }} <button class="text-btn" @click="fetchResults">Pokušaj ponovo</button>
        </p>
        <p v-else-if="!list.length" class="status" role="status">
          Nema salona za odabrane filtere. Pokušaj s drugim datumom ili gradom.
        </p>
        <div v-else class="card-grid"><SalonCard v-for="s in list" :key="s.slug" :salon="s" /></div>
      </div>
      <SalonMap v-if="showMap" :salons="loading ? [] : list" :location="locationPoint" />
    </div>
  </template>
  <template v-else>
    <section class="editorial-section">
      <div class="section-title">
        <div>
          <h2>Izdvojeni saloni</h2>
        </div>
        <a class="outline" href="/pretraga">Svi saloni</a>
      </div>
      <p v-if="loading" class="status" role="status">Učitavamo salone…</p>
      <p v-else-if="error" class="status" role="alert">
        {{ error }} <button class="text-btn" @click="fetchResults">Pokušaj ponovo</button>
      </p>
      <p v-else-if="!premium.length" class="status">Trenutno nema izdvojenih salona.</p>
      <div v-else class="card-grid">
        <SalonCard v-for="s in premium.slice(0, 6)" :key="s.slug" :salon="s" />
      </div>
    </section>
    <section v-if="top.length" class="editorial-section">
      <div class="section-title">
        <div>
          <h2>Najbolje ocijenjeni</h2>
        </div>
        <a class="outline" href="/pretraga?sort=rating">Pogledaj sve</a>
      </div>
      <div class="card-grid"><SalonCard v-for="s in top" :key="s.slug" :salon="s" /></div>
    </section>
  </template>
</template>
