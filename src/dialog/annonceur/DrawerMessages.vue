<template>
  <v-navigation-drawer v-model="model" location="right" temporary width="420">
    <v-toolbar density="comfortable" :title="title || 'Messages candidature'" />
    <div class="pa-3">
      <div v-if="!messages?.length" class="text-medium-emphasis text-body-2 mb-3">
        Aucun message pour le moment.
      </div>

      <div v-else class="mb-3" style="max-height: 60vh; overflow:auto;">
        <div v-for="m in messages" :key="m.id" class="mb-2">
          <div class="text-caption text-medium-emphasis">
            {{ m.role }} • {{ formatDate(m.created_at) }}
          </div>
          <div class="pa-2 rounded bg-grey-lighten-4">{{ m.contenu }}</div>
        </div>
      </div>

      <v-textarea
        v-model="draft"
        rows="3"
        placeholder="Écrire un message…"
        variant="outlined"
        class="mb-2"
      />
      <v-btn :loading="sending" block color="primary" @click="onSend">
        Envoyer
      </v-btn>
    </div>
  </v-navigation-drawer>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

type Msg = { id:number; role:string; user_id:number; contenu:string; created_at:string }

const props = defineProps<{
  modelValue: boolean
  title?: string
  messages: Msg[]
  sending?: boolean
}>()

const emit = defineEmits<{
  (e:'update:modelValue', v:boolean): void
  (e:'send', text:string): void
}>()

const model = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v)
})

const draft = ref('')

function onSend() {
  const text = draft.value.trim()
  if (!text) return
  emit('send', text)
  draft.value = ''
}

function formatDate(iso: string) {
  try { return new Date(iso).toLocaleString() } catch { return iso }
}
</script>
