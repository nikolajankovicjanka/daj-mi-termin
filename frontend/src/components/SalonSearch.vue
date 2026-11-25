<script setup lang="ts">
import { ref } from 'vue';
import Icon from './Icon.vue';

const query = defineModel<string>('query', { required: true });
const city = defineModel<string>('city', { required: true });
defineProps<{ locating: boolean; message: string }>();
const emit = defineEmits<{ search: []; locate: []; cityInput: [] }>();
const cityInput = ref<HTMLInputElement>();
defineExpose({ focusCity: () => cityInput.value?.focus() });
</script>
<template>
  <form class="finder" role="search" @submit.prevent="emit('search')">
    <label
    ><span>Usluga ili salon</span
    ><span class="finder-input"
    ><Icon name="search" /><input
        v-model="query"
        type="search"
        placeholder="Npr. šišanje ili naziv salona" /></span
    ></label>
    <label
    ><span>Grad</span
    ><span class="finder-input"
    ><Icon name="pin" /><input
        v-model="city"
        ref="cityInput"
        autocomplete="address-level2"
        placeholder="Npr. Bijeljina"
        @input="emit('cityInput')" /></span
    ></label>
    <button type="button" class="location-action" :disabled="locating" @click="emit('locate')">
      <Icon name="locate" :size="16" />{{
        locating ? 'Tražimo lokaciju…' : 'Koristi moju lokaciju'
      }}
    </button>
    <button class="primary find-action">Pronađi termin <span aria-hidden="true">→</span></button>
    <p v-if="message" role="status">{{ message }}</p>
  </form>
</template>
