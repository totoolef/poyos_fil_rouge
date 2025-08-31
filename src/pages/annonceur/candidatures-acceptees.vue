<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <div class="d-flex align-center justify-space-between mb-2">
        <h2 class="text-h6 mb-0">Mes candidatures</h2>
        <v-select
          v-model="filtreStatut"
          :items="statutsFiltres"
          label="Filtrer par statut"
          variant="outlined"
          prepend-icon="mdi-filter"
          class="mb-0"
          clearable
          style="max-width: 260px"
        />
      </div>

      <v-progress-linear v-if="enChargement" indeterminate color="primary" class="mb-4" />

      <v-data-table
        v-else
        :headers="entetes"
        :items="candidaturesFiltrees"
        :items-per-page="10"
        class="elevation-1 d-none d-md-table"
      >
        <template #item.conducteur="{ item }">
          {{ item.conducteur.prenom }} {{ item.conducteur.nom }}
        </template>

        <template #item.documents="{ item }">
          <v-chip :color="item.preflight.ok ? 'green' : 'orange'" size="small" variant="flat">
            {{ item.preflight.ok ? 'Complet' : 'Incomplet' }}
          </v-chip>
        </template>

        <template #item.photos="{ item }">
          <v-btn
            :disabled="!item.photos_count"
            :variant="item.photos_count ? 'outlined' : 'text'"
            :color="item.photos_count ? 'primary' : ''"
            size="small"
            @click="ouvrirPhotos(item)"
          >
            <v-icon start>mdi-camera</v-icon>
            {{ item.photos_count ? item.photos_count + ' photo(s)' : 'Aucune photo' }}
          </v-btn>
        </template>

        <template #item.statut="{ item }">
          <v-chip :color="couleurStatut(item.statut)" size="small">{{ labelStatut(item.statut) }}</v-chip>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            v-if="item.statut === 'en_attente'"
            size="small"
            variant="outlined"
            color="success"
            class="mr-2"
            @click="ouvrirDialogStatut(item, 'acceptee')"
          >Accepter</v-btn>
          <v-btn
            v-if="item.statut === 'en_attente'"
            size="small"
            variant="outlined"
            color="error"
            class="mr-2"
            @click="ouvrirDialogStatut(item, 'refusee')"
          >Refuser</v-btn>
          <v-btn
            size="small"
            variant="text"
            color="primary"
            @click="ouvrirDialogComplement(item)"
          >Demander compléments</v-btn>
          <v-btn
            size="small"
            variant="text"
            color="primary"
            class="ml-2"
            @click="ouvrirMessages(item)"
          >Messages</v-btn>
        </template>
      </v-data-table>

      <!-- Cartes mobiles -->
      <v-row v-if="display.smAndDown" class="d-md-none mt-2">
        <v-col v-for="c in candidaturesFiltrees" :key="c.id" cols="12">
          <v-card class="elevation-2 pa-4 mb-3">
            <v-card-title class="pb-1">{{ c.annonce.titre }}</v-card-title>
            <v-card-subtitle class="pt-0">
              {{ c.conducteur.prenom }} {{ c.conducteur.nom }} — {{ c.vehicule.marque }} {{ c.vehicule.modele }}
            </v-card-subtitle>
            <v-card-text class="pt-2">
              <div class="mb-1">
                Statut :
                <v-chip :color="couleurStatut(c.statut)" size="small">{{ labelStatut(c.statut) }}</v-chip>
              </div>
              <div class="mb-1">
                Documents :
                <v-chip :color="c.preflight.ok ? 'green' : 'orange'" size="small" variant="flat">
                  {{ c.preflight.ok ? 'Complet' : 'Incomplet' }}
                </v-chip>
              </div>
              <div>
                Photos :
                <v-btn
                  class="ml-1"
                  :disabled="!c.photos_count"
                  :variant="c.photos_count ? 'outlined' : 'text'"
                  :color="c.photos_count ? 'primary' : ''"
                  size="small"
                  @click="ouvrirPhotos(c)"
                >
                  <v-icon start>mdi-camera</v-icon>
                  {{ c.photos_count ? c.photos_count + ' photo(s)' : 'Aucune photo' }}
                </v-btn>
              </div>
            </v-card-text>
            <v-card-actions>
              <v-btn v-if="c.statut === 'en_attente'" size="small" variant="outlined" color="success" class="mr-2"
                     @click="ouvrirDialogStatut(c, 'acceptee')">Accepter</v-btn>
              <v-btn v-if="c.statut === 'en_attente'" size="small" variant="outlined" color="error" class="mr-2"
                     @click="ouvrirDialogStatut(c, 'refusee')">Refuser</v-btn>
              <v-btn size="small" variant="text" color="primary" @click="ouvrirDialogComplement(c)">
                Demander compléments
              </v-btn>
              <v-spacer />
              <v-btn size="small" variant="text" color="primary" @click="ouvrirMessages(c)">
                Messages
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

      <v-alert v-if="messageErreur" type="error" class="mt-4">{{ messageErreur }}</v-alert>
      <v-alert v-if="!candidatures.length && !enChargement" type="info" class="mt-4">Aucune candidature.</v-alert>
    </v-card>

    <!-- Dialogs & Drawer -->
    <DialogPhotos
      v-model="dialogPhotos"
      :items="photosItems"
      :title="`Photos — ${dossierCourant?.conducteur?.prenom || ''} ${dossierCourant?.conducteur?.nom || ''}`"
    />

    <DialogStatut
      v-model="dialogStatut"
      :statut-cible="statutCible"
      :loading="loadingAction"
      :motif="motifStatut"
      @confirm="confirmerChangementStatut"
    />

    <DialogComplement
      v-model="dialogComplement"
      :loading="loadingAction"
      :message="messageComplement"
      @send="envoyerDemandeComplement"
    />

    <DrawerMessages
      v-model="drawerMessages"
      :title="`Messages — #${selection?.id || ''}`"
      :messages="messages"
      :sending="loadingMsg"
      @send="envoyerMessage"
    />

  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useDisplay } from 'vuetify'
import axios from 'axios'

import DialogStatut from '../../dialog/annonceur/DialogStatut.vue'
import DialogComplement from '../../dialog/annonceur/DialogComplement.vue'
import DialogPhotos from '../../dialog/annonceur/DialogPhotos.vue'
import DrawerMessages from '../../dialog/annonceur/DrawerMessages.vue'

const display = useDisplay()

/** ---------- Config API ---------- */
const API = 'http://localhost:8080'
const authHeaders = () => {
  const token = localStorage.getItem('token')
  return { Authorization: `Bearer ${token}` }
}

/** ---------- Types ---------- */
type StatutCand = 'en_attente'|'acceptee'|'refusee'
type CandidatureRow = {
  id: number
  statut: StatutCand
  annonce: { id:number; titre:string }
  conducteur: { id:number; nom:string; prenom:string }
  vehicule: { marque:string; modele:string; couleur:string }
  preflight: { ok:boolean }
  photos_count: number
}
type PhotoItem = { id:number; url:string }
type DossierDTO = {
  candidature: { id:number, conducteur:{ nom:string; prenom:string }, annonce:{ id:number; titre:string } },
  documents: { photos_vehicule: { items: PhotoItem[] } }
}

/** ---------- State tableau ---------- */
const enChargement = ref(true)
const messageErreur = ref<string | null>(null)
const filtreStatut = ref<string | null>('toutes')
const candidatures = ref<CandidatureRow[]>([])

const entetes = [
  { title: 'Annonce', key: 'annonce.titre' },
  { title: 'Conducteur', key: 'conducteur' },
  { title: 'Documents', key: 'documents', sortable: false },
  { title: 'Photos', key: 'photos', sortable: false },
  { title: 'Statut', key: 'statut' },
  { title: 'Actions', key: 'actions', sortable: false },
] as const

const statutsFiltres = ['toutes', 'en_attente', 'acceptee', 'refusee']

const candidaturesFiltrees = computed(() => {
  if (!filtreStatut.value || filtreStatut.value === 'toutes') return candidatures.value
  return candidatures.value.filter(c => c.statut === filtreStatut.value)
})

function couleurStatut(s: StatutCand) {
  if (s === 'acceptee') return 'green'
  if (s === 'refusee') return 'red'
  return 'orange'
}
function labelStatut(s: StatutCand) {
  if (s === 'acceptee') return 'Acceptée'
  if (s === 'refusee') return 'Refusée'
  return 'En attente'
}

/** ---------- API calls (inline axios) ---------- */
async function apiList() {
  return axios.get(`${API}/candidatures/annonce_candidatures.php`, { headers: authHeaders() })
}
async function apiDossier(candidatureId:number) {
  return axios.get(`${API}/candidatures/get_dossier_candidature.php`, {
    params: { candidature_id: candidatureId },
    headers: authHeaders()
  })
}
async function apiSetStatut(candidatureId:number, statut:StatutCand, motif?:string) {
  return axios.post(`${API}/candidatures/candidature_set_statut.php`, {
    candidature_id: candidatureId, statut, motif
  }, { headers: { ...authHeaders(), 'Content-Type':'application/json' }})
}
async function apiDemandeComplement(candidatureId:number, message:string) {
  return axios.post(`${API}/candidatures/candidature_demande_complement.php`, {
    candidature_id: candidatureId, message
  }, { headers: { ...authHeaders(), 'Content-Type':'application/json' }})
}
async function apiMessages(candidatureId:number, after?:string) {
  return axios.get(`${API}/candidatures/candidature_messages.php`, {
    params: { candidature_id: candidatureId, ...(after ? { after } : {}) },
    headers: authHeaders()
  })
}
async function apiSendMessage(candidatureId:number, message:string) {
  return axios.post(`${API}/candidatures/candidature_message.php`, {
    candidature_id: candidatureId, message
  }, { headers: { ...authHeaders(), 'Content-Type':'application/json' }})
}

/** ---------- Chargement tableau ---------- */
async function chargerCandidatures() {
  try {
    console.log('Chargement des candidatures...')
    enChargement.value = true
    const r = await apiList()
    console.log('Réponse API:', r.data)
    if (r.data?.success) candidatures.value = r.data.candidatures as CandidatureRow[]
    else messageErreur.value = r.data?.message || 'Échec du chargement'
  } catch (e) {
    console.error('Erreur lors du chargement:', e)
    messageErreur.value = 'Erreur de connexion'
  } finally {
    enChargement.value = false
  }
}
onMounted(() => {
  console.log('Page candidatures-acceptees montée')
  
  // Vérifier l'authentification
  const token = localStorage.getItem('token')
  const role = localStorage.getItem('role')
  
  console.log('Token présent:', !!token)
  console.log('Rôle:', role)
  
  if (!token || role !== 'annonceur') {
    console.error('Utilisateur non authentifié ou mauvais rôle')
    messageErreur.value = 'Veuillez vous connecter en tant qu\'annonceur'
    return
  }
  
  chargerCandidatures()
})

/** ---------- Photos ---------- */
const dialogPhotos = ref(false)
const dossierCourant = ref<DossierDTO['candidature'] | null>(null)
const photosItems = ref<PhotoItem[]>([])
async function ouvrirPhotos(row: CandidatureRow) {
  try {
    const r = await apiDossier(row.id)
    if (r.data?.success) {
      dossierCourant.value = r.data.candidature
      photosItems.value = r.data.documents?.photos_vehicule?.items || []
      dialogPhotos.value = true
    } else messageErreur.value = r.data?.message || 'Impossible de charger les photos'
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Erreur de connexion'
  }
}

/** ---------- Accepter / Refuser ---------- */
const selection = ref<{ id:number } | null>(null)
const dialogStatut = ref(false)
const statutCible = ref<StatutCand>('acceptee')
const motifStatut = ref('')
const loadingAction = ref(false)

function ouvrirDialogStatut(row: CandidatureRow, target: StatutCand) {
  selection.value = { id: row.id }
  statutCible.value = target
  motifStatut.value = ''
  dialogStatut.value = true
}
async function confirmerChangementStatut(motif?: string) {
  if (!selection.value) return
  try {
    loadingAction.value = true
    await apiSetStatut(selection.value.id, statutCible.value, motif)
    dialogStatut.value = false
    await chargerCandidatures()
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Échec du changement de statut'
  } finally {
    loadingAction.value = false
  }
}

/** ---------- Demande de compléments ---------- */
const dialogComplement = ref(false)
const messageComplement = ref('')
function ouvrirDialogComplement(row: CandidatureRow) {
  selection.value = { id: row.id }
  messageComplement.value = ''
  dialogComplement.value = true
}
async function envoyerDemandeComplement(text: string) {
  if (!selection.value || !text.trim()) return
  try {
    loadingAction.value = true
    await apiDemandeComplement(selection.value.id, text.trim())
    dialogComplement.value = false
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Échec de l’envoi'
  } finally {
    loadingAction.value = false
  }
}

/** ---------- Messages ---------- */
const drawerMessages = ref(false)
const messages = ref<Array<{id:number; role:string; user_id:number; contenu:string; created_at:string}>>([])
const loadingMsg = ref(false)

async function ouvrirMessages(row: CandidatureRow) {
  selection.value = { id: row.id }
  drawerMessages.value = true
  await chargerMessages()
}
async function chargerMessages() {
  if (!selection.value) return
  try {
    const r = await apiMessages(selection.value.id)
    if (r.data?.success) messages.value = r.data.messages
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Impossible de charger les messages'
  }
}
async function envoyerMessage(text: string) {
  if (!selection.value || !text.trim()) return
  try {
    loadingMsg.value = true
    await apiSendMessage(selection.value.id, text.trim())
    await chargerMessages()
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Échec d’envoi du message'
  } finally {
    loadingMsg.value = false
  }
}
</script>
