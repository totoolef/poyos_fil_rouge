<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <div class="d-flex align-center justify-space-between mb-2">
        <h2 class="text-h6 mb-0">Mes candidatures envoyées</h2>

        <div class="d-flex align-center" style="gap:12px">
          <v-chip
            :color="docsOk ? 'green' : 'orange'"
            size="small"
            variant="flat"
            class="mr-2"
          >
            {{ docsOk ? 'Documents complets' : 'Documents incomplets' }}
          </v-chip>
          <v-btn
            size="small"
            variant="outlined"
            color="primary"
            @click="ouvrirPhotosProfil"
          >
            <v-icon start>mdi-camera</v-icon> Mes photos véhicule
          </v-btn>
        </div>
      </div>

      <v-select
        v-model="filtreStatut"
        :items="statutsFiltres"
        label="Filtrer par statut"
        variant="outlined"
        prepend-icon="mdi-filter"
        class="mb-4"
        clearable
      />

      <v-progress-linear v-if="enChargement" indeterminate color="primary" class="mb-4" />

      <!-- Tableau desktop -->
      <v-data-table
        v-else
        :headers="entetes"
        :items="candidaturesFiltrees"
        :items-per-page="8"
        class="elevation-1 d-none d-md-table"
      >
        <template #item.message="{ item }">
          <span class="text-truncate d-inline-block" style="max-width: 360px;">
            {{ item.message || '—' }}
          </span>
        </template>

        <template #item.statut="{ item }">
          <v-chip :color="getStatutColor(item.statut)" size="small">
            {{ labelStatut(item.statut) }}
          </v-chip>
        </template>

        <template #item.docs>
          <v-chip :color="docsOk ? 'green' : 'orange'" size="small" variant="flat">
            {{ docsOk ? 'OK' : 'Manquants' }}
          </v-chip>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            size="small"
            variant="text"
            color="primary"
            class="mr-2"
            @click="ouvrirMessages(item)"
          >
            Messages
          </v-btn>
          <v-btn
            size="small"
            variant="text"
            color="primary"
            class="mr-2"
            @click="ouvrirPhotosProfil"
          >
            Photos
          </v-btn>
          <v-btn
            v-if="item.statut === 'en_attente'"
            size="small"
            variant="outlined"
            color="red"
            @click="annulerCandidature(item)"
          >
            Annuler
          </v-btn>
        </template>
      </v-data-table>

      <!-- Cartes mobiles -->
      <v-row v-if="display.smAndDown" class="d-md-none">
        <v-col v-for="c in candidaturesFiltrees" :key="c.id" cols="12">
          <v-card class="elevation-2 pa-4 mb-3">
            <v-card-title class="text-subtitle-1">{{ c.titre_annonce }}</v-card-title>
            <v-card-subtitle class="text-caption">{{ c.localisation }}</v-card-subtitle>
            <v-card-text>
              <div class="mb-2">
                <span class="text-medium-emphasis">Message : </span>
                <span>{{ c.message?.length ? c.message : '—' }}</span>
              </div>
              <div class="mb-2">
                <span class="text-medium-emphasis">Date : </span>
                <span>{{ formatDate(c.created_at) }}</span>
              </div>
              <div class="mb-2">
                <span class="text-medium-emphasis">Documents : </span>
                <v-chip :color="docsOk ? 'green' : 'orange'" size="small" variant="flat">
                  {{ docsOk ? 'OK' : 'Manquants' }}
                </v-chip>
              </div>
            </v-card-text>
            <v-card-actions>
              <v-chip :color="getStatutColor(c.statut)" size="small">{{ labelStatut(c.statut) }}</v-chip>
              <v-spacer />
              <v-btn size="small" variant="text" color="primary" @click="ouvrirMessages(c)">Messages</v-btn>
              <v-btn size="small" variant="text" color="primary" @click="ouvrirPhotosProfil">Photos</v-btn>
              <v-btn
                v-if="c.statut === 'en_attente'"
                size="small"
                variant="outlined"
                color="red"
                @click="annulerCandidature(c)"
              >
                Annuler
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

      <v-alert v-if="messageErreur" type="error" class="mt-4">{{ messageErreur }}</v-alert>
      <v-alert v-if="!candidaturesFiltrees.length && !enChargement" type="info" class="mt-4">
        Aucune candidature correspondant à vos critères.
      </v-alert>
    </v-card>

    <!-- Drawer messages & Dialog photos -->
    <DrawerMessages
      v-model="drawerMessages"
      :title="`Messages — #${selection?.id || ''}`"
      :messages="messages"
      :sending="loadingMsg"
      @send="envoyerMessage"
    />
    <DialogPhotos
      v-model="dialogPhotos"
      :items="photosItems"
      title="Mes photos véhicule"
    />
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useDisplay } from 'vuetify'
import DrawerMessages from '../../dialog/annonceur/DrawerMessages.vue'
import DialogPhotos from '../../dialog/annonceur/DialogPhotos.vue'

const display = useDisplay()

/** -------- Types -------- */
interface Candidature {
  id: number
  annonce_id: number
  titre_annonce: string
  localisation: string
  message: string
  statut: 'en_attente'|'refusee'|'annulee'|'acceptee'
  created_at: string
  updated_at: string
}
type Msg = { id:number; role:string; user_id:number; contenu:string; created_at:string }
type PhotoItem = { id:number; url:string }

/** -------- State -------- */
const candidatures = ref<Candidature[]>([])
const filtreStatut = ref<string | null>(null)
const enChargement = ref<boolean>(true)
const messageErreur = ref<string | null>(null)

const entetes = [
  { title: 'Titre annonce', key: 'titre_annonce' },
  { title: 'Localisation', key: 'localisation' },
  { title: 'Message', key: 'message' },
  { title: 'Date', key: 'created_at' },
  { title: 'Statut', key: 'statut' },
  { title: 'Docs', key: 'docs', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false },
] as const

const statutsFiltres = ['en_attente', 'refusee', 'annulee', 'acceptee']

const candidaturesFiltrees = computed(() => {
  if (filtreStatut.value) {
    return candidatures.value.filter(c => c.statut === filtreStatut.value)
  }
  return candidatures.value
})

function getStatutColor(statut: string) {
  switch (statut) {
    case 'en_attente': return 'warning'
    case 'acceptee': return 'success'
    case 'refusee': return 'error'
    case 'annulee': return 'grey'
    default: return 'grey'
  }
}
function labelStatut(statut: string) {
  if (statut === 'en_attente') return 'En attente'
  if (statut === 'acceptee') return 'Acceptée'
  if (statut === 'refusee') return 'Refusée'
  if (statut === 'annulee') return 'Annulée'
  return statut
}
function formatDate(iso: string) {
  try { return new Date(iso).toLocaleString() } catch { return iso }
}

/** -------- API helpers -------- */
const API = 'http://localhost:8080'
const authHeaders = () => {
  const token = localStorage.getItem('token')
  return { Authorization: `Bearer ${token}` }
}

/** -------- Charger candidatures -------- */
async function chargerCandidatures() {
  const token = localStorage.getItem('token')
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter'
    enChargement.value = false
    return
  }

  try {
    enChargement.value = true
    const r = await axios.get(`${API}/candidatures/get_candidatures_conducteur.php`, {
      headers: authHeaders()
    })
    if (r.data?.success) {
      candidatures.value = r.data.candidatures as Candidature[]
    } else {
      messageErreur.value = r.data?.message || 'Échec du chargement des candidatures'
    }
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Erreur de connexion au serveur'
  } finally {
    enChargement.value = false
  }
}

/** -------- Annuler candidature -------- */
async function annulerCandidature(c: Candidature) {
  if (!confirm('Voulez-vous vraiment annuler cette candidature ?')) return
  try {
    const r = await axios.post(`${API}/candidatures/annuler_candidature.php`, {
      candidature_id: c.id
    }, { headers: { ...authHeaders(), 'Content-Type':'application/json' }})
    if (r.data?.success) {
      await chargerCandidatures()
    } else {
      alert(r.data?.message || 'Échec de l’annulation')
    }
  } catch(e) {
    console.error(e)
    alert('Erreur de connexion au serveur')
  }
}

/** -------- Badge docs + Photos (profil conducteur) --------
 * On lit /documents/get_documents_conducteur.php (tes docs existants).
 * - docsOk : true si 4 docs requis OK + au moins 1 photo
 * - photosItems : array pour DialogPhotos
 */
const docsOk = ref(false)
const photosItems = ref<PhotoItem[]>([])
const dialogPhotos = ref(false)
async function chargerDocumentsProfil() {
  try {
    const r = await axios.get(`${API}/documents/get_documents_conducteur.php`, {
      headers: authHeaders()
    })
    if (r.data?.success) {
      const d = r.data.documents || {}

      const ok =
        d?.permis?.status === 'ok' &&
        d?.carte_grise?.status === 'ok' &&
        d?.assurance?.status === 'ok' &&
        d?.controle_technique?.status === 'ok'

      // Normalisation photos : accepte d.photos_vehicule.urls (string[]) ou d.photos_vehicule.items ({url}[])
      const urlsFromStrings: string[] = Array.isArray(d?.photos_vehicule?.urls)
        ? d.photos_vehicule.urls.filter((u: any) => typeof u === 'string')
        : []

      const urlsFromItems: string[] = Array.isArray(d?.photos_vehicule?.items)
        ? d.photos_vehicule.items
            .map((it: any) => it?.url)
            .filter((u: any) => typeof u === 'string')
        : []

      const allUrls: string[] = [...urlsFromStrings, ...urlsFromItems]
      photosItems.value = allUrls.map((u, i) => ({ id: i + 1, url: u }))

      docsOk.value = ok && photosItems.value.length > 0
    } else {
      // garde un fallback "incomplet" si l’appel échoue
      docsOk.value = false
      photosItems.value = []
    }
  } catch (e) {
    console.error(e)
    docsOk.value = false
    photosItems.value = []
  }
}


function ouvrirPhotosProfil() {
  dialogPhotos.value = true
}

/** -------- Messages (par candidature) -------- */
const drawerMessages = ref(false)
const selection = ref<Candidature | null>(null)
const messages = ref<Msg[]>([])
const loadingMsg = ref(false)

async function ouvrirMessages(c: Candidature) {
  selection.value = c
  drawerMessages.value = true
  await chargerMessages()
}

async function chargerMessages() {
  if (!selection.value) return
  try {
    const r = await axios.get(`${API}/candidatures/candidature_messages.php`, {
      params: { candidature_id: selection.value.id },
      headers: authHeaders()
    })
    if (r.data?.success) {
      messages.value = r.data.messages as Msg[]
    }
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Impossible de charger les messages'
  }
}

async function envoyerMessage(text: string) {
  if (!selection.value || !text.trim()) return
  try {
    loadingMsg.value = true
    await axios.post(`${API}/candidatures/candidature_message.php`, {
      candidature_id: selection.value.id,
      message: text.trim()
    }, { headers: { ...authHeaders(), 'Content-Type':'application/json' }})
    await chargerMessages()
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Échec d’envoi du message'
  } finally {
    loadingMsg.value = false
  }
}

/** -------- Mount -------- */
onMounted(async () => {
  await Promise.all([chargerCandidatures(), chargerDocumentsProfil()])
})
</script>

<style scoped>
.text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
