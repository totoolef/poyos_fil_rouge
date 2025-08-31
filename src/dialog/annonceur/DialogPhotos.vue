<template>
  <v-dialog v-model="model" max-width="900">
    <v-card>
      <v-card-title class="d-flex align-center">
        <span class="text-subtitle-1">{{ title || 'Photos du véhicule' }}</span>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" @click="model=false" />
      </v-card-title>

      <v-card-text>
        <v-row v-if="items?.length" class="mt-1">
          <v-col v-for="ph in items" :key="ph.id" cols="12" sm="6" md="4">
            <v-img 
              :src="ph.url" 
              aspect-ratio="16/9" 
              class="rounded" 
              cover
              @error="console.log('Erreur image:', ph.url)"
            />
          </v-col>
        </v-row>
        <v-alert v-else type="info">Aucune photo trouvée.</v-alert>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model=false">Fermer</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
type PhotoItem = { id:number; url:string }

const props = defineProps<{
  modelValue: boolean
  items?: PhotoItem[]
  title?: string
}>()

const emit = defineEmits<{ (e:'update:modelValue', v:boolean): void }>()

const model = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v)
})
</script>
