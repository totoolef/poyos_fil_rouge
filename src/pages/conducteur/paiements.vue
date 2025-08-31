<template>
  <v-container class="mt-8">
    <v-card>
      <v-card-title class="text-h5">
        Mes Paiements Mensuels
      </v-card-title>
      <v-card-text>
        <div v-if="loading" class="d-flex justify-center">
          <v-progress-circular indeterminate color="primary"></v-progress-circular>
        </div>

        <div v-else>
          <v-alert v-if="paiements.length === 0" type="info" variant="tonal">
            Aucun paiement mensuel pour l'instant.
          </v-alert>

              <v-table v-else density="comfortable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Campagne</th>
                    <th>Annonceur</th>
                    <th>Montant Reçu</th>
                    <th>Frais Plateforme</th>
                    <th>Date</th>
                    <th>Statut</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="paiement in paiements" :key="paiement.id">
                    <td>{{ paiement.id }}</td>
                    <td>{{ paiement.campagne_titre }}</td>
                    <td>{{ paiement.annonceur_nom }}</td>
                    <td class="font-weight-bold text-success">{{ paiement.montant_particulier }}€</td>
                    <td>{{ paiement.commission_poyos }}€</td>
                    <td>{{ formatDate(paiement.date_paiement) }}</td>
                    <td>
                      <v-chip :color="getStatutColor(paiement.statut)" size="small">
                        {{ getStatutLabel(paiement.statut) }}
                      </v-chip>
                    </td>
                  </tr>
                </tbody>
              </v-table>

          <!-- Résumé -->
          <v-card class="mt-4" variant="outlined">
            <v-card-title class="text-subtitle-1">Résumé</v-card-title>
            <v-card-text>
              <div class="d-flex justify-space-between">
                <div>
                  <strong>Total reçu :</strong> {{ totalRecu }}€
                </div>
                <div>
                  <strong>Paiements payés :</strong> {{ paiementsPayes }}
                </div>
                <div>
                  <strong>En attente :</strong> {{ paiementsEnAttente }}
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snack.show" :timeout="3000" :color="snack.color">
      {{ snack.text }}
    </v-snackbar>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const API = import.meta.env.VITE_API_URL || 'http://localhost:8000'

const loading = ref(false)
const paiements = ref<any[]>([])
const snack = ref({ show: false, text: '', color: 'success' })

const totalRecu = computed(() => {
  return paiements.value
    .filter(p => p.statut === 'paye')
    .reduce((sum, p) => sum + parseFloat(p.montant_particulier), 0)
    .toFixed(2)
})

const paiementsPayes = computed(() => {
  return paiements.value.filter(p => p.statut === 'paye').length
})

const paiementsEnAttente = computed(() => {
  return paiements.value.filter(p => p.statut === 'en_attente').length
})

function formatDate(date: string) {
  if (!date) return 'Date non définie'
  try {
    return new Date(date).toLocaleDateString('fr-FR')
  } catch {
    return 'Date invalide'
  }
}

function getStatutColor(statut: string) {
  switch (statut) {
    case 'paye': return 'success'
    case 'en_attente': return 'warning'
    case 'refuse': return 'error'
    default: return 'grey'
  }
}

function getStatutLabel(statut: string) {
  switch (statut) {
    case 'paye': return 'Payé'
    case 'en_attente': return 'En attente'
    case 'refuse': return 'Refusé'
    default: return statut
  }
}

async function loadPaiements() {
  try {
    loading.value = true
    const { data } = await axios.get(`${API}/paiements/get_paiements_conducteur.php`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` }
    })
    
    if (!data?.success) {
      throw new Error(data?.message || 'Erreur chargement paiements')
    }
    
    paiements.value = data.data.paiements || []
  } catch (e: any) {
    snack.value = { 
      show: true, 
      text: e?.response?.data?.message || e?.message || 'Erreur chargement paiements', 
      color: 'error' 
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadPaiements()
})
</script>
