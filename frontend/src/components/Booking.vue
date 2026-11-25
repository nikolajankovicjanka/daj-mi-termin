<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { api, money, post, today, type Studio } from '../api';
import Icon from './Icon.vue';
const props = defineProps<{ salon: Studio; selected: number | null }>();
type Slot = { employee_id: number; employee_name: string; start_at: string; label: string };
const selectedService = ref<number | null>(props.selected || props.salon.services[0]?.id || null),
  worker = ref(''),
  date = ref(today());
const slots = ref<Slot[]>([]),
  pick = ref<Slot | null>(null),
  name = ref(''),
  email = ref(''),
  error = ref(''),
  loading = ref(false),
  saving = ref(false),
  done = ref('');
const chosen = computed(() => props.salon.services.find((s) => s.id === selectedService.value));
let request = 0;
watch(
  () => props.selected,
  (id) => {
    if (id) {
      selectedService.value = id;
      done.value = '';
    }
  },
);
async function load() {
  const id = ++request;
  pick.value = null;
  slots.value = [];
  error.value = '';
  if (!selectedService.value || !date.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  const params = new URLSearchParams({
    service_id: String(selectedService.value),
    date: date.value,
  });
  if (worker.value) params.set('employee_id', worker.value);
  try {
    const response = await api<Slot[]>(
      '/salons/' + encodeURIComponent(props.salon.slug) + '/slots?' + params,
    );
    if (id === request) slots.value = response;
  } catch (e) {
    if (id === request) error.value = (e as Error).message;
  } finally {
    if (id === request) loading.value = false;
  }
}
watch([selectedService, worker, date], load, { immediate: true });
async function book() {
  if (!pick.value || !chosen.value || saving.value) return;
  saving.value = true;
  error.value = '';
  const slot = pick.value;
  try {
    await post('/salons/' + encodeURIComponent(props.salon.slug) + '/bookings', {
      employee_id: slot.employee_id,
      service_id: chosen.value.id,
      start_at: slot.start_at,
      customer_name: name.value.trim(),
      customer_email: email.value.trim(),
    });
    done.value =
      chosen.value.name +
      ' · ' +
      date.value.split('-').reverse().join('.') +
      '. u ' +
      slot.label +
      ' · ' +
      slot.employee_name;
  } catch (e) {
    const msg = (e as Error).message;
    await load();
    error.value = msg;
  } finally {
    saving.value = false;
  }
}
</script>
<template>
  <aside id="rezervacija" class="booking panel">
    <p class="eyebrow"><Icon name="calendar" :size="16" />REZERVACIJA</p>
    <h2>{{ done ? 'Termin je rezervisan' : 'Odaberite termin' }}</h2>
    <div v-if="done" role="status">
      <p><Icon name="check" /> {{ done }}</p>
      <p>{{ salon.name }}, {{ salon.address }}, {{ salon.city }}</p>
      <p>Potvrda rezervacije stiže na {{ email }}.</p>
      <button
        class="quiet"
        @click="
          done = '';
          load();
        "
      >
        Nova rezervacija
      </button>
    </div>
    <form v-else-if="salon.services.length" @submit.prevent="book">
      <label
        >Usluga<select v-model="selectedService">
          <option v-for="s in salon.services" :key="s.id" :value="s.id">
            {{ s.name }} · {{ money(s.price) }}
          </option>
        </select></label
      >
      <label
        >Zaposleni<select v-model="worker">
          <option value="">Bilo ko od dostupnih</option>
          <option v-for="w in salon.employees" :key="w.id" :value="String(w.id)">
            {{ w.name }}
          </option>
        </select></label
      >
      <label>Datum<input type="date" required v-model="date" :min="today()" /></label>
      <fieldset>
        <legend>Slobodni termini</legend>
        <p v-if="loading">Tražimo slobodne termine…</p>
        <p v-else-if="!slots.length">Nema slobodnih termina za ovaj izbor.</p>
        <div v-else class="slots">
          <button
            v-for="s in slots"
            :key="s.start_at + '-' + s.employee_id"
            type="button"
            :class="{ selected: pick === s }"
            :aria-pressed="pick === s"
            @click="pick = s"
          >
            {{ s.label }}<small>{{ s.employee_name }}</small>
          </button>
        </div>
      </fieldset>
      <template v-if="pick"
        ><label
          >Ime i prezime<input required maxlength="100" autocomplete="name" v-model="name" /></label
        ><label
          >Email za potvrdu<input
            type="email"
            required
            maxlength="254"
            autocomplete="email"
            v-model="email"
        /></label>
        <p class="summary">
          {{ chosen?.duration }} min · {{ money(chosen?.price || 0) }} · {{ pick.label }}
        </p>
        <button class="primary" :disabled="saving">
          {{ saving ? 'Rezervišemo…' : 'Potvrdi rezervaciju' }}
        </button></template
      >
      <p class="error" v-if="error" role="alert">{{ error }}</p>
    </form>
    <p v-else>Salon još nije dodao usluge.</p>
  </aside>
</template>
