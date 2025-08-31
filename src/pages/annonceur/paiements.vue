<template>
  <v-container class="mt-8">
    <v-card>
      <v-card-title class="text-h5">
        Mes Paiements
      </v-card-title>
      <v-card-text>
        <div v-if="loading" class="d-flex justify-center">
          <v-progress-circular indeterminate color="primary"></v-progress-circular>
        </div>

        <div v-else>
          <v-alert v-if="paiements.length === 0" type="info" variant="tonal">
            Aucun paiement pour l'instant.
          </v-alert>

                        <v-table v-else density="comfortable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Conducteur</th>
                    <th>Montant Total</th>
                    <th>Frais Plateforme</th>
                    <th>Montant Net</th>
                    <th>Date</th>
                    <th>Statut</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="paiement in paiements" :key="paiement.id">
                    <td>{{ paiement.id }}</td>
                    <td>
                      <v-chip :color="getTypeColor(paiement.type)" size="small">
                        {{ getTypeLabel(paiement.type) }}
                      </v-chip>
                    </td>
                    <td>{{ paiement.conducteur_nom }}</td>
                    <td>{{ paiement.montant_total }}€</td>
                    <td>
                      <span v-if="paiement.type === 'mensuel'">{{ paiement.commission_poyos }}€</span>
                      <span v-else class="text-grey">—</span>
                    </td>
                    <td>{{ paiement.montant_particulier }}€</td>
                    <td>{{ formatDate(paiement.date_paiement) }}</td>
                    <td>
                      <v-chip :color="getStatutColor(paiement.statut)" size="small">
                        {{ paiement.statut }}
                      </v-chip>
                    </td>
                  </tr>
                </tbody>
              </v-table>
        </div>
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snack.show" :timeout="3000" :color="snack.color">
      {{ snack.text }}
    </v-snackbar>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const API = import.meta.env.VITE_API_URL || 'http://localhost:8000'

const loading = ref(false)
const paiements = ref<any[]>([])
const snack = ref({ show: false, text: '', color: 'success' })

function formatDate(date: string) {
  if (!date) return 'Date non définie'
  try {
    return new Date(date).toLocaleDateString('fr-FR')
  } catch {
    return 'Date invalide'
  }
}

function getTypeColor(type: string) {
  switch (type) {
    case 'mensuel': return 'success'
    case 'pose': return 'info'
    case 'creation': return 'primary'
    case 'impression': return 'warning'
    default: return 'grey'
  }
}

function getTypeLabel(type: string) {
  switch (type) {
    case 'mensuel': return 'Paiement mensuel'
    case 'pose': return 'Pose'
    case 'creation': return 'Création'
    case 'impression': return 'Impression'
    default: return type
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

async function loadPaiements() {
  try {
    loading.value = true
    const { data } = await axios.get(`${API}/paiements/get_paiements_annonceur.php`, {
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
