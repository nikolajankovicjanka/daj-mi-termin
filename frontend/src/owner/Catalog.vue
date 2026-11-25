<script setup lang="ts">
import { ref } from 'vue';
import {
  api,
  days,
  dayKey,
  errorText,
  type Workspace,
  type Employee,
  type Service,
  type Hours,
} from './api';
const props = defineProps<{ kind: 'services' | 'employees'; ws: Workspace }>(),
  emit = defineEmits<{ saved: [] }>();
const isService = props.kind === 'services',
  id = ref(0),
  editing = ref(false),
  busy = ref(false),
  error = ref(''),
  message = ref('');
const name = ref(''),
  price = ref<number | string>(0),
  duration = ref(30),
  hours = ref<Hours>({});
const defaultHours = () =>
  Object.fromEntries(['1', '2', '3', '4', '5'].map((k) => [k, ['09:00', '17:00']]));
function edit(item?: Employee | Service) {
  error.value = '';
  message.value = '';
  editing.value = true;
  id.value = item?.id || 0;
  name.value = item?.name || '';
  if (item && 'duration' in item) {
    price.value = item.price;
    duration.value = item.duration;
  } else if (item && 'working_hours' in item) {
    hours.value = Object.fromEntries(
      Object.entries(item.working_hours || {})
        .filter(([, v]) => Array.isArray(v) && v.length === 2)
        .map(([k, v]) => [k, [...v]]),
    );
  } else {
    price.value = 0;
    duration.value = 30;
    hours.value = defaultHours();
  }
}
function toggle(k: string) {
  if (hours.value[k]) delete hours.value[k];
  else hours.value[k] = ['09:00', '17:00'];
}
async function save() {
  error.value = '';
  busy.value = true;
  try {
    await api(
      props.kind + (id.value ? '/' + id.value : ''),
      id.value ? 'PUT' : 'POST',
      isService
        ? { name: name.value, price: price.value, duration: duration.value }
        : { name: name.value, working_hours: hours.value },
    );
    emit('saved');
    editing.value = false;
    message.value = 'Izmjene su sačuvane.';
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
async function remove(item: Employee | Service) {
  if (!window.confirm('Ukloniti „' + item.name + '“?')) return;
  error.value = '';
  message.value = '';
  busy.value = true;
  try {
    await api(props.kind + '/' + item.id, 'DELETE');
    if (id.value === item.id) editing.value = false;
    emit('saved');
    message.value = 'Stavka je uklonjena.';
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <section>
    <div class="ow-title">
      <h2>{{ isService ? 'Usluge i cijene' : 'Radnici i smjene' }}</h2>
      <button @click="edit()">{{ isService ? '+ Dodaj uslugu' : '+ Dodaj radnika' }}</button>
    </div>
    <p v-if="error" class="ow-error" role="alert">{{ error }}</p>
    <p v-if="message" class="ow-success" role="status">{{ message }}</p>
    <form v-if="editing" class="ow-card" @submit.prevent="save">
      <h3>{{ id ? 'Izmjena' : 'Novi unos' }}</h3>
      <div class="ow-grid">
        <label
          >{{ isService ? 'Naziv usluge' : 'Ime radnika'
          }}<input v-model.trim="name" required maxlength="120"
        /></label>
        <template v-if="isService"
          ><label
            >Cijena (KM)<input
              type="number"
              v-model.number="price"
              required
              min="0"
              max="99999.99"
              step="0.01" /></label
          ><label
            >Trajanje u minutama<input
              type="number"
              v-model.number="duration"
              required
              min="5"
              max="720" /></label
        ></template>
      </div>
      <fieldset v-if="!isService" class="ow-shifts">
        <legend>Sedmično radno vrijeme</legend>
        <div v-for="(day, i) in days" :key="day">
          <label class="ow-check"
            ><input type="checkbox" :checked="!!hours[dayKey(i)]" @change="toggle(dayKey(i))" />{{
              day
            }}</label
          ><template v-if="hours[dayKey(i)]"
            ><input
              v-model="hours[dayKey(i)][0]"
              type="time"
              required
              :aria-label="day + ' od'" /><input
              v-model="hours[dayKey(i)][1]"
              type="time"
              required
              :aria-label="day + ' do'" /></template
          ><small v-else>Neradni dan</small>
        </div>
      </fieldset>
      <div class="ow-actions">
        <button :disabled="busy">Sačuvaj</button
        ><button type="button" class="ow-ghost" @click="editing = false">Odustani</button>
      </div>
    </form>
    <div class="ow-card">
      <p v-if="!(isService ? ws.services : ws.employees).length" class="ow-empty">
        {{
          isService
            ? 'Još nema usluga. Dodajte prvu uslugu i cijenu.'
            : 'Dodajte prvog radnika i odredite njegove smjene.'
        }}
      </p>
      <article v-for="item in isService ? ws.services : ws.employees" :key="item.id" class="ow-row">
        <div>
          <strong>{{ item.name }}</strong
          ><small v-if="'duration' in item"
            >{{ item.duration }} min · {{ Number(item.price).toFixed(2) }} KM</small
          ><small v-else
            >{{
              Object.values(item.working_hours || {}).filter((h) => h?.length === 2).length
            }}
            radnih dana sedmično</small
          >
        </div>
        <div class="ow-actions">
          <button class="ow-ghost" @click="edit(item)">Uredi</button
          ><button class="ow-link" :disabled="busy" @click="remove(item)">Ukloni</button>
        </div>
      </article>
    </div>
  </section>
</template>
