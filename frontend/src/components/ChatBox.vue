<script setup lang="ts">
import { ref, onBeforeUnmount, onMounted } from 'vue';
import { api, post } from '../api';
import Icon from './Icon.vue';
const props = defineProps<{ slug: string }>();
type Message = { sender: string; body: string; created_at: string };
type Thread = { token: string; messages: Message[] };
const storageKey = 'termin:chat:' + props.slug;
const token = ref(sessionStorage.getItem(storageKey) || ''),
  open = ref(false),
  name = ref(''),
  body = ref(''),
  messages = ref<Message[]>([]),
  error = ref(''),
  busy = ref(false);
let timer = 0,
  refreshNumber = 0;
async function refresh() {
  if (!token.value || !open.value || busy.value) return;
  const id = ++refreshNumber;
  try {
    const data = await api<Thread>('/chats/' + encodeURIComponent(token.value));
    if (id === refreshNumber) {
      messages.value = data.messages;
      error.value = '';
    }
  } catch (e) {
    if (id === refreshNumber) error.value = (e as Error).message;
  }
}
async function send() {
  if (busy.value || !body.value.trim()) return;
  busy.value = true;
  ++refreshNumber;
  error.value = '';
  try {
    const data = token.value
      ? await post<Thread>('/chats/' + encodeURIComponent(token.value), { body: body.value.trim() })
      : await post<Thread>('/salons/' + encodeURIComponent(props.slug) + '/chats', {
          customer_name: name.value.trim(),
          body: body.value.trim(),
        });
    if (data.token) {
      token.value = data.token;
      sessionStorage.setItem(storageKey, data.token);
    }
    messages.value = data.messages;
    body.value = '';
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
onMounted(() => {
  timer = window.setInterval(refresh, 6000);
});
onBeforeUnmount(() => clearInterval(timer));
</script>
<template>
  <section class="chat panel">
    <button
      class="chat-trigger"
      :aria-expanded="open"
      @click="
        open = !open;
        refresh();
      "
    >
      <Icon name="chat" /> Razgovor sa salonom <span>{{ open ? '−' : '+' }}</span>
    </button>
    <div v-if="open">
      <ol class="messages" aria-live="polite">
        <li
          v-for="(m, i) in messages"
          :key="i"
          :class="{ mine: m.sender === 'customer' || m.sender === 'client' }"
        >
          <b>{{ m.sender === 'customer' || m.sender === 'client' ? 'Vi' : 'Salon' }}</b>
          <p>{{ m.body }}</p>
        </li>
      </ol>
      <form @submit.prevent="send">
        <label v-if="!token">Vaše ime<input v-model="name" required maxlength="100" /></label
        ><label>Poruka<textarea v-model="body" required maxlength="1000" rows="2" /></label
        ><button class="quiet" :disabled="busy">Pošalji poruku</button>
      </form>
      <p class="error" v-if="error" role="alert">{{ error }}</p>
    </div>
  </section>
</template>
