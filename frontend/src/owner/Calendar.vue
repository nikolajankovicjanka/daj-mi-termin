<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { api, errorText, localDate, time, type Booking, type Slot, type Workspace } from './api';
const props = defineProps<{ ws: Workspace }>();
const date = ref(localDate()),
  staff = ref(0),
  all = ref<Booking[]>([]),
  pending = ref(false),
  loading = ref(false),
  error = ref(''),
  notice = ref('');
const editing = ref(false),
  service = ref(props.ws.services[0]?.id || 0),
  employee = ref(props.ws.employees[0]?.id || 0);
const slots = ref<Slot[]>([]),
  select = ref(''),
  slotLoading = ref(false),
  name = ref(''),
  email = ref(''),
  source = ref('phone');
const bookings = computed(() =>
  all.value.filter((b) => !staff.value || b.employee_id === staff.value),
);
const staffName = (id: number) =>
  props.ws.employees.find((e) => e.id === id)?.name || 'Arhivirani radnik';
const statusLabels: Record<string, string> = {
  confirmed: 'Zakazano',
  completed: 'Završeno',
  cancelled: 'Otkazano',
};
let seq = 0,
  slotSeq = 0;
async function fetchDay() {
  const n = ++seq;
  error.value = '';
  loading.value = true;
  all.value = [];
  try {
    const rows = await api<Booking[]>('calendar?date=' + date.value);
    if (n === seq) all.value = rows;
  } catch (e) {
    if (n === seq) error.value = errorText(e);
  } finally {
    if (n === seq) loading.value = false;
  }
}
async function fetchSlots() {
  const n = ++slotSeq;
  slots.value = [];
  select.value = '';
  slotLoading.value = false;
  if (!editing.value || !employee.value || !service.value || !date.value) return;
  slotLoading.value = true;
  try {
    const rows = await api<Slot[]>(
      'slots?' +
        new URLSearchParams({
          date: date.value,
          employee_id: String(employee.value),
          service_id: String(service.value),
        }),
    );
    if (n === slotSeq) slots.value = rows;
  } catch (e) {
    if (n === slotSeq) error.value = errorText(e);
  } finally {
    if (n === slotSeq) slotLoading.value = false;
  }
}
watch(date, fetchDay, { immediate: true });
watch([date, service, employee, editing], fetchSlots);
function toggleForm() {
  notice.value = '';
  error.value = '';
  editing.value = !editing.value;
}
async function book() {
  pending.value = true;
  error.value = '';
  notice.value = '';
  try {
    await api('bookings', 'POST', {
      service_id: service.value,
      employee_id: employee.value,
      start_at: select.value,
      customer_name: name.value,
      customer_email: email.value || null,
      source: source.value,
    });
    editing.value = false;
    name.value = '';
    email.value = '';
    await fetchDay();
    notice.value = 'Termin je zakazan.';
  } catch (e) {
    const msg = errorText(e);
    await fetchSlots();
    error.value = msg;
  } finally {
    pending.value = false;
  }
}
async function mark(b: Booking, status: string) {
  if (status === 'cancelled' && !window.confirm('Otkazati termin za ' + b.customer_name + '?'))
    return;
  pending.value = true;
  error.value = '';
  notice.value = '';
  try {
    await api('bookings/' + b.id, 'PATCH', { status });
    await fetchDay();
    if (editing.value) await fetchSlots();
    notice.value = status === 'completed' ? 'Termin je završen.' : 'Termin je otkazan.';
  } catch (e) {
    error.value = errorText(e);
  } finally {
    pending.value = false;
  }
}
function moveDate(n: number) {
  const d = new Date(date.value + 'T12:00:00Z');
  if (Number.isNaN(d.getTime())) return;
  d.setUTCDate(d.getUTCDate() + n);
  date.value = localDate(d);
}
</script>
<template>
  <section>
    <div class="ow-title">
      <div>
        <h2>Raspored termina</h2>
        <small>Vrijeme u zoni Europe/Sarajevo</small>
      </div>
      <button @click="toggleForm">{{ editing ? 'Zatvori unos' : '+ Dodaj termin' }}</button>
    </div>
    <div class="ow-tools">
      <div class="ow-date">
        <button class="ow-ghost" aria-label="Prethodni dan" @click="moveDate(-1)">‹</button
        ><input v-model="date" type="date" required aria-label="Dan" /><button
          class="ow-ghost"
          aria-label="Sljedeći dan"
          @click="moveDate(1)"
        >
          ›
        </button>
      </div>
      <select v-model.number="staff" aria-label="Filter po radniku">
        <option :value="0">Svi radnici</option>
        <option v-for="e in ws.employees" :key="e.id" :value="e.id">{{ e.name }}</option>
      </select>
    </div>
    <p v-if="error" role="alert" class="ow-error">{{ error }}</p>
    <p v-if="notice" role="status" class="ow-success">{{ notice }}</p>
    <form v-if="editing" class="ow-card" @submit.prevent="book">
      <h3>Ručna rezervacija</h3>
      <p v-if="!ws.employees.length || !ws.services.length">
        Za zakazivanje dodajte uslugu i radnika sa radnim vremenom.
      </p>
      <template v-else
        ><div class="ow-grid">
          <label
            >Usluga<select v-model.number="service">
              <option v-for="s in ws.services" :value="s.id" :key="s.id">
                {{ s.name }} · {{ s.duration }} min
              </option>
            </select></label
          ><label
            >Radnik<select v-model.number="employee">
              <option v-for="e in ws.employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select></label
          >
          <label
            >Način zakazivanja<select v-model="source">
              <option value="phone">Telefonom</option>
              <option value="walk_in">Lično u salonu</option>
            </select></label
          ><label
            >Slobodno vrijeme<select v-model="select" required>
              <option disabled value="">
                {{
                  slotLoading
                    ? 'Učitavanje…'
                    : slots.length
                      ? 'Izaberite termin'
                      : 'Nema slobodnih termina'
                }}
              </option>
              <option v-for="s in slots" :key="s.start_at" :value="s.start_at">
                {{ s.label }}
              </option>
            </select></label
          >
          <label>Ime klijenta<input v-model.trim="name" required maxlength="120" /></label
          ><label
            >Email za potvrdu (opciono)<input type="email" v-model.trim="email" maxlength="255"
          /></label>
        </div>
        <button :disabled="pending || !select">Sačuvaj termin</button></template
      >
    </form>
    <div class="ow-card">
      <h3>{{ bookings.length }} termina</h3>
      <p v-if="loading">Učitavanje rasporeda…</p>
      <p v-else-if="!bookings.length" class="ow-empty">Za izabrani dan nema zakazanih termina.</p>
      <article v-for="b in bookings" :key="b.id" class="ow-booking">
        <div>
          <b>{{ time(b.start_at) }} – {{ time(b.end_at) }}</b
          ><small>{{ staffName(b.employee_id) }}</small>
        </div>
        <div>
          <strong>{{ b.customer_name }}</strong
          ><small
            >{{ b.service_name }} ·
            {{
              b.source === 'phone' ? 'Telefon' : b.source === 'walk_in' ? 'Lično' : 'Online'
            }}</small
          >
        </div>
        <div class="ow-actions">
          <span class="ow-status">{{ statusLabels[b.status] || b.status }}</span
          ><template v-if="b.status === 'confirmed'"
            ><button
              v-if="new Date(b.end_at).getTime() <= Date.now()"
              class="ow-ghost"
              :disabled="pending"
              @click="mark(b, 'completed')"
            >
              Završi</button
            ><button class="ow-link" :disabled="pending" @click="mark(b, 'cancelled')">
              Otkaži
            </button></template
          >
        </div>
      </article>
    </div>
  </section>
</template>
