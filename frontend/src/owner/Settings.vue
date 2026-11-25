<script setup lang="ts">
import { reactive, ref } from 'vue';
import { api, errorText, planNames, type Workspace } from './api';
const props = defineProps<{ ws: Workspace }>(),
  emit = defineEmits<{ saved: [] }>();
const s = reactive({ ...props.ws.salon }),
  email = ref(props.ws.email),
  current = ref(''),
  pass = ref(''),
  confirmPass = ref('');
const end = [s.free_until, s.expires_at]
  .filter((v): v is string => !!v)
  .sort()
  .at(-1);
const photo = ref<File>(),
  cover = ref<File>(),
  busy = ref(false),
  error = ref(''),
  notice = ref('');
function pick(e: Event, kind: string) {
  const f = (e.target as HTMLInputElement).files?.[0];
  if (kind === 'photo') photo.value = f;
  else cover.value = f;
}
async function save(kind: string) {
  busy.value = true;
  error.value = '';
  notice.value = '';
  try {
    if (kind === 'profile') {
      const form = new FormData();
      for (const k of [
        'name',
        'address',
        'city',
        'latitude',
        'longitude',
        'color',
        'official_email',
        'billing_email',
      ] as const)
        form.set(k, String(s[k] ?? ''));
      if (photo.value) form.set('photo', photo.value);
      if (cover.value && s.plan !== 'basic') form.set('cover', cover.value);
      await api('profile', 'POST', form);
    } else {
      await api('account', 'PUT', {
        email: email.value,
        current_password: current.value,
        password: pass.value || null,
        password_confirmation: confirmPass.value || null,
      });
      current.value = '';
      pass.value = '';
      confirmPass.value = '';
    }
    notice.value = 'Izmjene su sačuvane.';
    emit('saved');
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
      <h2>Podešavanja</h2>
      <span>{{ planNames[s.plan] }}{{ end ? ' · do ' + end.slice(0, 10) : '' }}</span>
    </div>
    <p v-if="error" class="ow-error" role="alert">{{ error }}</p>
    <p v-if="notice" class="ow-success" role="status">{{ notice }}</p>
    <form class="ow-card" @submit.prevent="save('profile')">
      <h3>Javni profil salona</h3>
      <div class="ow-grid">
        <label>Naziv firme<input v-model.trim="s.name" required maxlength="150" /></label
        ><label>Grad<input v-model.trim="s.city" required maxlength="100" /></label
        ><label>Adresa<input v-model.trim="s.address" required maxlength="255" /></label>
        <label>Službeni email<input v-model.trim="s.official_email" type="email" required /></label
        ><label
          >Email za pretplatu<input v-model.trim="s.billing_email" type="email" required /></label
        ><label>Boja salona<input type="color" v-model="s.color" /></label>
        <label
          >Geografska širina<input
            v-model="s.latitude"
            type="number"
            min="-90"
            max="90"
            step="any" /></label
        ><label
          >Geografska dužina<input
            v-model="s.longitude"
            type="number"
            min="-180"
            max="180"
            step="any"
        /></label>
        <label
          >Profilna slika (do 4 MB)<input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="pick($event, 'photo')" /></label
        ><label v-if="s.plan !== 'basic'"
          >Naslovna slika (do 6 MB)<input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="pick($event, 'cover')"
        /></label>
      </div>
      <button :disabled="busy">Sačuvaj profil</button>
    </form>
    <form class="ow-card" @submit.prevent="save('account')">
      <h3>Pristupni podaci</h3>
      <div class="ow-grid">
        <label
          >Email za prijavu<input
            v-model.trim="email"
            type="email"
            required
            autocomplete="username" /></label
        ><label
          >Trenutna lozinka<input
            v-model="current"
            type="password"
            required
            autocomplete="current-password" /></label
        ><label
          >Nova lozinka (opciono)<input
            v-model="pass"
            type="password"
            minlength="10"
            maxlength="72"
            autocomplete="new-password" /></label
        ><label
          >Potvrdi novu lozinku<input
            v-model="confirmPass"
            :required="!!pass"
            type="password"
            autocomplete="new-password"
        /></label>
      </div>
      <button :disabled="busy">Sačuvaj pristupne podatke</button>
    </form>
  </section>
</template>
