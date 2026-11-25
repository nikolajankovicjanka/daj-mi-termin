<script setup lang="ts">
defineProps<{ name: string; size?: number }>();
const shapes: Record<string, string> = {
  search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
  pin: '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.7"/>',
  star: '<path d="m12 2.7 2.8 5.7 6.3.9-4.6 4.5 1.1 6.3-5.6-3-5.6 3 1.1-6.3L3 9.3l6.2-.9Z"/>',
  user: '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v5m10-5v5M3 11h18"/>',
  locate:
    '<circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2.5"/><path d="M12 1v4m0 14v4M1 12h4m14 0h4"/>',
  grid: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
  scissors:
    '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m8 8 12 12M8 16 20 4"/>',
  barber: '<path d="M8 4h8v16H8zM7 2h10M7 22h10M8 10l8-5m-8 11 8-5m-6 9 6-4"/>',
  hair: '<path d="M4 15c2-2 1-7 4-10 2-3 7-3 10 0 4 4 0 10 3 14M6 17c3-1 4-5 4-8m3-3c-2 6 4 8 2 14m1-13c2 3-1 6 2 10"/>',
  physio:
    '<circle cx="14" cy="4" r="2"/><path d="m5 10 5-2 4 1 4 3 3-1M12 9l-2 6 4 2 1 5m-5-7-3 6H3"/>',
  nail: '<path d="M8 11V7a4 4 0 0 1 8 0v4M8 11h8v7a4 4 0 0 1-8 0ZM10 7a2 2 0 0 1 2-2"/>',
  foot: '<path d="M12 11c2-2 4-1 5 1 1 4-2 5-3 7s-2 3-4 2c-3-2 0-5 1-7Z"/><circle cx="16" cy="6" r="2.2"/><path d="m11 5 .3 1m-3 1 .4 1m-2 2 .5 .8"/>',
  eye: '<path d="M2 10c5-5 13-6 20-1M3 15c5-4 12-6 18-1"/><path d="M8 13a4 4 0 0 0 8 0"/>',
  spine: '<path d="M15 2c-6 5 0 11-5 20M10 4l7 1M9 8l7 1M9 12l7 1m-8 3 7 1m-9 3 6 1"/>',
  back: '<path d="M20 12H4m6-6-6 6 6 6"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  chat: '<path d="M21 11a9 9 0 0 1-9 9c-2 0-3-.3-5-1l-4 2 1-5a9 9 0 1 1 17-5Z"/>',
  check: '<path d="m4 12 5 5L20 6"/>',
  spark: '<path d="m12 2 2.6 7.4L22 12l-7.4 2.6L12 22l-2.6-7.4L2 12l7.4-2.6Z"/>',
  map: '<path d="m2 5 7-3 6 3 7-3v17l-7 3-6-3-7 3Zm7-3v17m6-14v17"/>',
};
</script>
<template>
  <svg
    aria-hidden="true"
    :width="size || 20"
    :height="size || 20"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.65"
    stroke-linecap="round"
    stroke-linejoin="round"
    v-html="shapes[name] || shapes.grid"
  />
</template>
