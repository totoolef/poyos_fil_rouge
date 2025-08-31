<template>
  <v-dialog v-model="model" max-width="520">
    <v-card>
      <v-card-title class="text-subtitle-1">
        {{ statutCible === 'acceptee' ? 'Accepter la candidature' : 'Refuser la candidature' }}
      </v-card-title>

      <v-card-text>
        <div class="mb-3">
          <div class="text-body-2">Motif (optionnel)</div>
          <v-textarea v-model="motifLocal" rows="3" variant="outlined" />
        </div>
        <v-alert type="warning" variant="tonal">Cette action est définitive.</v-alert>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model=false">Annuler</v-btn>
        <v-btn :loading="loading" color="primary" @click="$emit('confirm', motifLocal || undefined)">Confirmer</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

type StatutCand = 'en_attente'|'acceptee'|'refusee'

const props = defineProps<{
  modelValue: boolean
  statutCible: StatutCand
  loading?: boolean
  motif?: string
}>()

const emit = defineEmits<{
  (e:'update:modelValue', v:boolean): void
  (e:'confirm', motif?: string): void
}>()

const model = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v)
})

const motifLocal = ref(props.motif || '')
watch(() => props.motif, (m) => { motifLocal.value = m || '' })
</script>
