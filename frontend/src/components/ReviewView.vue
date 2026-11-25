<script setup lang="ts">
import { ref } from 'vue';
import { post } from '../api';
import Icon from './Icon.vue';
const props = defineProps<{ token: string }>(),
  rating = ref(5),
  comment = ref(''),
  done = ref(false),
  error = ref(''),
  busy = ref(false);
async function submit() {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await post('/reviews/' + encodeURIComponent(props.token), {
      rating: rating.value,
      comment: comment.value.trim(),
    });
    done.value = true;
  } catch (e) {
    error.value = (e as Error).message;
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <main class="review panel">
    <p class="eyebrow"><Icon name="star" />OCJENA TERMINA</p>
    <h1>{{ done ? 'Hvala na ocjeni!' : 'Kako je prošao vaš termin?' }}</h1>
    <p v-if="done">Vaša recenzija je sačuvana. <a href="/">Pogledajte salone</a></p>
    <form v-else @submit.prevent="submit">
      <fieldset>
        <legend>Vaša ocjena</legend>
        <div class="rating-options">
          <label v-for="n in 5" :key="n"
            ><input type="radio" name="rating" :value="n" v-model="rating" />{{ n }} ★</label
          >
        </div>
      </fieldset>
      <label
        >Komentar <small>(opciono)</small
        ><textarea rows="4" maxlength="1000" v-model="comment" /></label
      ><button class="primary" :disabled="busy">Pošalji ocjenu</button>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
    </form>
  </main>
</template>
