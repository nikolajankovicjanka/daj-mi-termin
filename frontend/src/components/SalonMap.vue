<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import type { Salon } from '../api';
const props = defineProps<{ salons: Salon[]; location: { lat: number; lng: number } | null }>();
const mapEl = ref<HTMLElement>(),
  failure = ref('');
const key = import.meta.env.VITE_GOOGLE_MAPS_KEY;
type LatLng = { lat: number; lng: number };
type Marker = { setMap: (map: null) => void; addListener: (event: string, fn: () => void) => void };
type MapInstance = {
  fitBounds: (bounds: unknown, padding: number) => void;
  setCenter: (p: LatLng) => void;
  setZoom: (z: number) => void;
};
type GMaps = {
  Map: new (el: HTMLElement, opts: object) => MapInstance;
  Marker: new (opts: object) => Marker;
  LatLngBounds: new () => { extend: (p: LatLng) => void };
};
let map: MapInstance | undefined,
  markers: Marker[] = [],
  active = true;
function maps(): GMaps | undefined {
  return (window as unknown as { google?: { maps?: GMaps } }).google?.maps;
}
function refresh() {
  const gm = maps();
  if (!gm || !map || !mapEl.value) return;
  markers.forEach((m) => m.setMap(null));
  markers = [];
  const points: LatLng[] = [];
  props.salons.forEach((s) => {
    if (s.latitude == null || s.longitude == null) return;
    const position = { lat: Number(s.latitude), lng: Number(s.longitude) };
    if (!Number.isFinite(position.lat) || !Number.isFinite(position.lng)) return;
    const m = new gm.Marker({ position, map, title: s.name });
    m.addListener('click', () => {
      window.location.href = '/studio/' + encodeURIComponent(s.slug);
    });
    markers.push(m);
    points.push(position);
  });
  if (props.location) {
    markers.push(
      new gm.Marker({ position: props.location, map, title: 'Vaša lokacija', label: '●' }),
    );
    points.push(props.location);
  }
  if (points.length > 1) {
    const bounds = new gm.LatLngBounds();
    points.forEach((p) => bounds.extend(p));
    map.fitBounds(bounds, 36);
  } else {
    map.setCenter(points[0] || { lat: 44.756, lng: 19.218 });
    map.setZoom(points.length ? 14 : 8);
  }
}
async function load() {
  if (!key) {
    failure.value = 'Mapa trenutno nije dostupna. Salone možete pregledati u rezultatima.';
    return;
  }
  try {
    if (!maps())
      await new Promise<void>((resolve, reject) => {
        const w = window as unknown as { terminMapReady?: () => void };
        w.terminMapReady = resolve;
        const s = document.createElement('script');
        s.src =
          'https://maps.googleapis.com/maps/api/js?key=' +
          encodeURIComponent(key) +
          '&callback=terminMapReady&loading=async';
        s.onerror = () => reject(new Error());
        s.async = true;
        document.head.appendChild(s);
      });
    const gm = maps();
    if (!gm || !mapEl.value || !active) return;
    map = new gm.Map(mapEl.value, {
      center: { lat: 44.756, lng: 19.218 },
      zoom: 10,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: true,
    });
    refresh();
  } catch {
    failure.value = 'Učitavanje Google mape nije uspjelo.';
  }
}
watch(() => [props.salons, props.location], refresh);
onMounted(load);
onBeforeUnmount(() => {
  active = false;
  markers.forEach((m) => m.setMap(null));
});
</script>
<template>
  <div class="map-wrap">
    <p v-if="failure" role="status">{{ failure }}</p>
    <div ref="mapEl" :hidden="!!failure" class="map-canvas" aria-label="Mapa salona" />
  </div>
</template>
