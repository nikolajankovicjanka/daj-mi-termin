<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { api, errorText, time, type Thread, type Message } from './api';
const threads = ref<Thread[]>([]),
  messages = ref<Message[]>([]),
  active = ref(0),
  draft = ref(''),
  error = ref(''),
  busy = ref(false),
  ready = ref(false);
let timer: ReturnType<typeof setInterval> | undefined,
  sequence = 0;
async function read() {
  const n = ++sequence,
    id = active.value;
  if (!id) return;
  try {
    const list = await api<Message[]>('threads/' + id);
    if (n === sequence) messages.value = list;
  } catch (e) {
    if (n === sequence) error.value = errorText(e);
  }
}
async function select(id: number) {
  active.value = id;
  messages.value = [];
  draft.value = '';
  await read();
}
async function refresh() {
  try {
    threads.value = await api<Thread[]>('threads');
    if (!active.value && threads.value[0]) await select(threads.value[0].id);
    else await read();
    ready.value = true;
  } catch (e) {
    error.value = errorText(e);
  }
}
async function reply() {
  const id = active.value;
  const body = draft.value.trim();
  if (!body) return;
  busy.value = true;
  error.value = '';
  try {
    await api('threads/' + id, 'POST', { body });
    if (active.value === id) {
      draft.value = '';
      await read();
    }
  } catch (e) {
    error.value = errorText(e);
  } finally {
    busy.value = false;
  }
}
onMounted(() => {
  void refresh();
  timer = setInterval(refresh, 10000);
});
onUnmounted(() => clearInterval(timer));
</script>
<template>
  <section>
    <div class="ow-title"><h2>Poruke klijenata</h2></div>
    <p v-if="error" class="ow-error" role="alert">{{ error }}</p>
    <p v-if="ready && !threads.length" class="ow-card">Još nema razgovora sa klijentima.</p>
    <div v-else-if="threads.length" class="ow-inbox">
      <aside class="ow-card">
        <button
          v-for="t in threads"
          :key="t.id"
          :class="t.id === active ? '' : 'ow-ghost'"
          @click="select(t.id)"
        >
          {{ t.customer_name }}
        </button>
      </aside>
      <div class="ow-card">
        <h3>{{ threads.find((t) => t.id === active)?.customer_name }}</h3>
        <ol>
          <li v-for="m in messages" :key="m.id" :class="{ outgoing: m.sender === 'salon' }">
            <small>{{ m.sender === 'salon' ? 'Vi' : 'Klijent' }} · {{ time(m.created_at) }}</small
            >{{ m.body }}
          </li>
        </ol>
        <form @submit.prevent="reply">
          <label>Odgovor<textarea v-model.trim="draft" required maxlength="2000"></textarea></label
          ><button :disabled="busy || !draft">Pošalji</button>
        </form>
      </div>
    </div>
  </section>
</template>
