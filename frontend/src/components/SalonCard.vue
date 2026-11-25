<script setup lang="ts">
import { money, photo, type Salon } from '../api';
import Icon from './Icon.vue';
defineProps<{ salon: Salon }>();
</script>
<template>
  <a
      :href="'/studio/' + encodeURIComponent(salon.slug)"
      class="salon-card"
      :class="'tier-' + salon.plan"
  >
    <div class="card-photo">
      <img :src="photo(salon, true)" alt="" loading="lazy" />
      <span v-if="salon.plan === 'premium'" class="plan"
      ><Icon name="spark" :size="13" />Premium</span
      >
    </div>
    <h3>{{ salon.name }}</h3>
    <p class="address">
      {{ [salon.category, salon.city].filter(Boolean).join(' · ')
      }}<template v-if="salon.distance_km != null">
      ·
      {{
        Number(salon.distance_km).toLocaleString('sr-Latn-BA', { maximumFractionDigits: 1 })
      }}
      km</template
    >
    </p>
    <div
        v-if="(salon.reviews_count > 0 && salon.rating != null) || salon.min_price != null"
        class="card-details"
    >
      <span v-if="salon.reviews_count > 0 && salon.rating != null" class="score"
      ><Icon name="star" :size="15" />{{
          Number(salon.rating).toLocaleString('sr-Latn-BA', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
          })
        }}
        <small>(broj recenzija: {{ salon.reviews_count }})</small></span
      >
      <span v-if="salon.min_price != null"
      >od <strong>{{ money(salon.min_price) }}</strong></span
      >
    </div>
  </a>
</template>
