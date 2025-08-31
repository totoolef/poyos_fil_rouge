<template>
  <v-dialog v-model="model" max-width="640">
    <v-card>
      <v-card-title class="text-subtitle-1">Demander des compléments</v-card-title>

      <v-card-text>
        <v-textarea
          v-model="messageLocal"
          label="Message à envoyer au conducteur"
          rows="4"
          variant="outlined"
          :rules="[v => !!v || 'Message requis']"
        />
        <div class="text-caption text-medium-emphasis mt-2">
          Exemples : “Merci d’ajouter la carte grise et une photo latérale.”
        </div>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model=false">Annuler</v-btn>
        <v-btn :loading="loading" color="primary" @click="$emit('send', messageLocal.trim())">Envoyer</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  modelValue: boolean
  loading?: boolean
  message?: string
}>()

const emit = defineEmits<{
  (e:'update:modelValue', v:boolean): void
  (e:'send', message: string): void
}>()

const model = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v)
})

const messageLocal = ref(props.message || '')
watch(() => props.message, (m) => { messageLocal.value = m || '' })
</script>
