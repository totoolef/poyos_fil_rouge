<template>
  <v-container class="py-6 mt-8" fluid>
    <v-card class="mx-auto" max-width="900">
      <v-card-title class="text-h6">Déposer un brief</v-card-title>
      <v-card-subtitle>
        Sélectionne la candidature acceptée, renseigne le brief et ajoute tes logos/ressources.
      </v-card-subtitle>

      <v-divider class="my-2" />

      <v-card-text>
        <v-form v-model="isValid" @submit.prevent="onSubmit">
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.candidatureId"
                :rules="[rules.required, rules.numeric]"
                label="ID de la candidature"
                variant="outlined"
                placeholder="Ex: 42"
                required
              />
            </v-col>

            <v-col cols="12" md="6">
              <v-combobox
                v-model="form.zones"
                :items="zonesOptions"
                label="Zones à couvrir"
                variant="outlined"
                multiple chips clearable
                hint="Ex: Portes avant, Portes arrière, Capot, Coffre, Vitres arrière"
                persistent-hint
              />
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="form.contraintes"
                label="Contraintes (brandbook, interdits, mentions légales, etc.)"
                variant="outlined"
                rows="4"
                auto-grow
              />
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="form.commentaires"
                label="Commentaires pour le graphiste"
                variant="outlined"
                rows="3"
                auto-grow
              />
            </v-col>

            <v-col cols="12">
              <v-file-input
                v-model="form.logos"
                label="Logos / éléments (PNG, JPG, SVG, PDF)"
                variant="outlined"
                accept=".png,.jpg,.jpeg,.webp,.svg,.pdf"
                multiple
                show-size
                prepend-icon="mdi-file-upload"
              />
            </v-col>
          </v-row>

          <v-card-actions class="justify-end">
            <v-btn :loading="loading" color="primary" type="submit">Déposer le brief</v-btn>
          </v-card-actions>
        </v-form>
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snackbar.show" :timeout="3000" :color="snackbar.color">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const url = 'http://localhost:8080/' // même style que le reste du projet

const isValid = ref(false)
const loading = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' })

const zonesOptions = [
  'Portes avant',
  'Portes arrière',
  'Capot',
  'Coffre',
  'Vitres arrière',
  'Pare-chocs',
]

const form = ref<{
  candidatureId: string | number | null
  contraintes: string
  commentaires: string
  zones: string[]
  logos: File[] | null
}>({
  candidatureId: null,
  contraintes: '',
  commentaires: '',
  zones: [],
  logos: null,
})

const rules = {
  required: (v: any) => (v !== null && v !== '' && v !== undefined) || 'Champ requis',
  numeric:  (v: any) => /^\d+$/.test(String(v ?? '')) || 'Doit être un nombre',
}

// Si la route fournit ?candidatureId=xx on préremplit
onMounted(() => {
  const url = new URL(window.location.href)
  const cand = url.searchParams.get('candidatureId')
  if (cand) form.value.candidatureId = cand
})

async function onSubmit() {
  if (!isValid.value || !form.value.candidatureId) return
  try {
    loading.value = true

    const fd = new FormData()
    fd.append('candidature_id', String(form.value.candidatureId))

    // Soit on envoie champs_json directement, soit on laisse le PHP le composer
    const champs = {
      contraintes: form.value.contraintes,
      zones: form.value.zones,
      commentaires: form.value.commentaires,
    }
    fd.append('champs_json', JSON.stringify(champs))

    if (form.value.logos && form.value.logos.length) {
      for (const file of form.value.logos) {
        fd.append('logos[]', file)
      }
    }

    const token = localStorage.getItem('token') || ''
    const { data } = await axios.post(`${url}briefs/deposer_brief.php`, fd, {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    })

    if (data?.success) {
      snackbar.value = { show: true, text: 'Brief déposé avec succès', color: 'success' }
      // reset simple
      form.value.contraintes = ''
      form.value.commentaires = ''
      form.value.zones = []
      form.value.logos = null
    } else {
      throw new Error(data?.message || 'Échec du dépôt du brief')
    }
  } catch (e: any) {
    snackbar.value = { show: true, text: e?.message || 'Erreur réseau', color: 'error' }
  } finally {
    loading.value = false
  }
}
</script>
