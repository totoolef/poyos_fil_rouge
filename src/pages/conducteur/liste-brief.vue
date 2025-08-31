<template>
  <v-container class="py-6 mt-8" fluid>
    <v-card class="mx-auto" max-width="1200">
      <v-card-title class="text-h6 d-flex align-center justify-space-between">
        <span>Mon Brief & Validation BAT — Candidature {{ candidatureId || '(à choisir)' }}</span>
        <div class="d-flex align-center" style="gap:.5rem;">
          <v-text-field
            v-model="candidatureId"
            label="ID candidature"
            variant="outlined"
            density="comfortable"
            style="max-width:220px"
            :rules="[rules.required, rules.numeric]"
            hide-details="auto"
          />
          <v-btn color="primary" :loading="loading" @click="loadAll">Charger</v-btn>
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text>
        <v-row class="mt-2" dense>
          <v-col cols="12" md="5">
            <v-card variant="outlined" class="mb-4">
              <v-card-title class="text-subtitle-1">Brief</v-card-title>
              <v-card-text>
                <v-alert type="info" variant="tonal" v-if="!brief && !loading">
                  Aucun brief trouvé pour cette candidature.
                </v-alert>

                <template v-if="brief">
                  <div class="mb-2"><b>Créé le :</b> {{ formatDate(brief.created_at) }}</div>
                  <div class="mb-2"><b>Contraintes :</b> {{ brief.champs_json?.contraintes || '—' }}</div>
                  <div class="mb-2">
                    <b>Zones :</b>
                    <v-chip
                      v-for="(z,i) in (brief.champs_json?.zones || [])"
                      :key="i" size="small" class="ma-1" color="primary" variant="tonal"
                    >{{ z }}</v-chip>
                    <span v-if="!(brief.champs_json?.zones?.length)">—</span>
                  </div>
                  <div class="mb-2"><b>Commentaires :</b> {{ brief.champs_json?.commentaires || '—' }}</div>
                </template>
              </v-card-text>
            </v-card>

            <v-card variant="outlined">
              <v-card-title class="text-subtitle-1">Statut & validations</v-card-title>
              <v-card-text>
                <div class="d-flex flex-wrap" style="gap:.4rem" v-if="status">
                  <v-chip :color="statutColor(status.statut)" variant="flat">
                    {{ labelStatut(status.statut) }}
                  </v-chip>
                  <v-chip :color="status.valide_annonceur ? 'success' : 'grey'" variant="tonal">
                    Annonceur {{ status.valide_annonceur ? 'validé' : '—' }}
                  </v-chip>
                  <v-chip :color="status.valide_conducteur ? 'success' : 'grey'" variant="tonal">
                    Conducteur {{ status.valide_conducteur ? 'validé' : '—' }}
                  </v-chip>
                  <v-chip v-if="lastAsset" variant="tonal">
                    Dernier asset : {{ (lastAsset.type || '').toUpperCase() }} v{{ lastAsset.version }}
                  </v-chip>
                </div>

                <v-alert
                  v-if="status?.statut === 'pret_a_commander'"
                  type="success" variant="tonal" class="mt-3"
                >
                  Les validations sont complètes. L’annonceur planifiera la pose.
                </v-alert>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="7">
            <v-card variant="outlined" class="mb-4">
              <v-card-title class="text-subtitle-1 d-flex align-center justify-space-between">
                <span>Aperçu BAT</span>
                <v-btn
                  v-if="batHref"
                  :href="batHref"
                  target="_blank"
                  rel="noopener"
                  variant="text"
                  prepend-icon="mdi-open-in-new"
                >
                  Ouvrir dans un onglet
                </v-btn>
              </v-card-title>
              <v-card-text>
                <v-alert type="warning" variant="tonal" v-if="!batHref && !loading">
                  Aucun BAT disponible pour le moment.
                </v-alert>

                <v-alert
                  v-if="pdfTooLarge"
                  type="warning"
                  variant="tonal"
                  class="mb-3"
                >
                  Le PDF BAT est volumineux. Utilise le bouton “Ouvrir dans un onglet” si l’aperçu est lent.
                </v-alert>

                <div v-if="batHref" class="pdf-wrapper">
                  <object :data="batHref" type="application/pdf" class="pdf-object">
                    <a :href="batHref" target="_blank" rel="noopener">Voir le BAT</a>
                  </object>
                </div>
              </v-card-text>
            </v-card>

            <v-card variant="outlined">
              <v-card-title class="text-subtitle-1">Actions</v-card-title>
              <v-card-text class="d-flex flex-wrap" style="gap:.5rem">
                <v-btn
                  color="success"
                  :disabled="!canValidate || loading"
                  :loading="loadingValidate"
                  prepend-icon="mdi-check-circle"
                  @click="validerBAT"
                >
                  Valider le BAT
                </v-btn>

                <v-btn
                  color="warning"
                  variant="outlined"
                  :disabled="!canAskChanges || loading"
                  prepend-icon="mdi-comment-edit-outline"
                  @click="dialogModifs = true"
                >
                  Demander des modifications
                </v-btn>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialogModifs" max-width="600">
      <v-card>
        <v-card-title class="text-h6">Demander des modifications</v-card-title>
        <v-card-text>
          <v-textarea v-model="commentaire" label="Commentaire" variant="outlined" rows="4" auto-grow />
        </v-card-text>
        <v-card-actions class="justify-end">
          <v-btn variant="text" @click="dialogModifs=false">Annuler</v-btn>
          <v-btn color="warning" :loading="loadingModifs" @click="demanderModifs">Envoyer</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :timeout="3000" :color="snackbar.color">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import axios from 'axios'
import { useUserStore } from '../../stores/utilisateurStores'

const API = 'http://localhost:8080'
const userStore = useUserStore()
const role = computed(() => (userStore.user?.role || '').toLowerCase())

const loading = ref(false)
const loadingValidate = ref(false)
const loadingModifs = ref(false)
const candidatureId = ref<string | number | null>(null)

const brief = ref<any | null>(null)
const assets = ref<any[]>([])
const status = ref<{ statut:string, valide_annonceur:boolean, valide_conducteur:boolean }|null>(null)

const snackbar = ref({ show:false, text:'', color:'success' })
const dialogModifs = ref(false)
const commentaire = ref('')

const rules = {
  required: (v:any)=> (v!==null && v!=='' && v!==undefined) || 'Champ requis',
  numeric:  (v:any)=> /^\d+$/.test(String(v ?? '')) || 'Doit être un nombre',
}

onMounted(()=>{
  const url = new URL(window.location.href)
  const cand = url.searchParams.get('candidatureId')
  if (cand) { candidatureId.value = cand; loadAll() }
})

const lastAsset = computed(() => assets.value.length ? assets.value[assets.value.length-1] : null)
const batAsset = computed(() => {
  const list = assets.value.filter(a => (a.type || '').toLowerCase() === 'bat')
  return list.length ? list[list.length-1] : null
})

/* DataURL -> blob URL (fiable partout) */
function dataUrlToBlobUrl(dataUrl: string) {
  try {
    const [meta, b64] = dataUrl.split(',')
    const mime = (meta.match(/data:([^;]+);base64/i) || [,'application/pdf'])[1]
    const bin = atob(b64 || '')
    const arr = new Uint8Array(bin.length)
    for (let i=0;i<bin.length;i++) arr[i] = bin.charCodeAt(i)
    return URL.createObjectURL(new Blob([arr], { type: mime }))
  } catch { return '' }
}

/* Href pour bouton/aperçu (blob pour data:, sinon absolu/relatif) */
const batHref = computed(() => {
  const url = String(batAsset.value?.url || '')
  if (!url) return ''
  if (url.startsWith('data:')) return dataUrlToBlobUrl(url)
  if (/^https?:\/\//i.test(url)) return url
  return `${API}/${url.replace(/^\/+/, '')}`
})

/* Revoke blobs (évite fuite mémoire) */
let lastBlob: string | null = null
watch(batHref, (val) => {
  if (lastBlob && lastBlob.startsWith('blob:') && lastBlob !== val) URL.revokeObjectURL(lastBlob)
  lastBlob = val || null
})
onBeforeUnmount(() => { if (lastBlob && lastBlob.startsWith('blob:')) URL.revokeObjectURL(lastBlob) })

const canValidate = computed(() => {
  if (!batAsset.value || !status.value) return false
  if (role.value === 'conducteur') return !status.value.valide_conducteur
  if (role.value === 'annonceur') return !status.value.valide_annonceur
  return false
})
const canAskChanges = computed(() => !!batHref.value)

// Fonction pour estimer la taille d'une data URL (non utilisée actuellement)
// function estimateDataUrlSizeBytes(url: string) {
//   try {
//     const base64 = (url.split(',')[1] || '').replace(/=+$/,'')
//     return Math.floor(base64.length * 3 / 4)
//   } catch { return 0 }
// }
const pdfTooLarge = computed(() => {
  const u = String(batAsset.value?.url || '')
  if (!u.startsWith('data:')) return false
  const base64 = (u.split(',')[1] || '').replace(/=+$/,'')
  const size = Math.floor(base64.length * 3 / 4)
  return size > 15 * 1024 * 1024
})

function formatDate(iso:string){ try { return new Date(iso).toLocaleString() } catch { return iso } }
function labelStatut(s?:string){
  switch(s){
    case 'en_brief': return 'En brief'
    case 'maquette_en_cours': return 'Maquette en cours'
    case 'bat_disponible': return 'BAT disponible'
    case 'bat_modifs': return 'Modifs demandées'
    case 'pret_a_commander': return 'Prêt à commander'
    case 'pose_planifiee': return 'Pose planifiée'
    case 'pose_effectuee': return 'Pose effectuée'
    case 'campagne_active': return 'Campagne active'
    default: return s || '—'
  }
}
function statutColor(s?:string){
  switch(s){
    case 'bat_modifs': return 'warning'
    case 'pret_a_commander': return 'success'
    case 'campagne_active': return 'success'
    case 'bat_disponible': return 'info'
    case 'maquette_en_cours': return 'primary'
    case 'en_brief': return 'grey'
    default: return 'grey'
  }
}

async function loadAll(){
  if(!candidatureId.value) return
  try{
    loading.value = true
    const token = localStorage.getItem('token') || ''
    const [briefRes, assetsRes] = await Promise.all([
      axios.get(`${API}/briefs/get_brief.php`, { params:{ candidature_id:candidatureId.value }, headers:{ Authorization:`Bearer ${token}` }}),
      axios.get(`${API}/briefs/lister_design_assets.php`, { params:{ candidature_id:candidatureId.value }, headers:{ Authorization:`Bearer ${token}` }})
    ])
    if(briefRes.data?.success) brief.value = briefRes.data.data?.brief || null
    else throw new Error(briefRes.data?.message || 'Erreur brief')

    if(assetsRes.data?.success) assets.value = assetsRes.data.data?.assets || []
    else throw new Error(assetsRes.data?.message || 'Erreur assets')

    const statusRes = await axios.get(`${API}/briefs/get_design_status.php`, {
      params:{ candidature_id:candidatureId.value },
      headers:{ Authorization:`Bearer ${token}` }
    })
    if(statusRes.data?.success){
      status.value = {
        statut: statusRes.data.data?.statut,
        valide_annonceur: !!statusRes.data.data?.valide_annonceur,
        valide_conducteur: !!statusRes.data.data?.valide_conducteur,
      }
    } else throw new Error(statusRes.data?.message || 'Erreur statut')
  } catch(e:any){
    // Ne pas afficher d'erreur si c'est une erreur 401 (token expiré)
    if (e.response?.status !== 401) {
      snackbar.value = { show:true, text:e?.message || 'Erreur réseau', color:'error' }
    }
  } finally {
    loading.value = false
  }
}

async function validerBAT(){
  if(!candidatureId.value) return
  try{
    loadingValidate.value = true
    const token = localStorage.getItem('token') || ''
    const { data } = await axios.post(`${API}/briefs/valider_bat.php`, {
      candidature_id:Number(candidatureId.value)
    }, { headers:{ Authorization:`Bearer ${token}` } })
    if(data?.success){
      snackbar.value = { show:true, text:'Validation enregistrée', color:'success' }
      await loadAll()
    } else throw new Error(data?.message || 'Échec validation')
  } catch(e:any){
    // Ne pas afficher d'erreur si c'est une erreur 401 (token expiré)
    if (e.response?.status !== 401) {
      snackbar.value = { show:true, text:e?.message || 'Erreur réseau', color:'error' }
    }
  } finally {
    loadingValidate.value = false
  }
}

async function demanderModifs(){
  if(!candidatureId.value) return
  try{
    loadingModifs.value = true
    const token = localStorage.getItem('token') || ''
    const { data } = await axios.post(`${API}/briefs/demander_modifs_bat.php`, {
      candidature_id:Number(candidatureId.value),
      commentaire: commentaire.value || ''
    }, { headers:{ Authorization:`Bearer ${token}` }})
    if(data?.success){
      snackbar.value = { show:true, text:'Demande envoyée', color:'warning' }
      dialogModifs.value = false
      commentaire.value = ''
      await loadAll()
    } else throw new Error(data?.message || 'Échec demande modifs')
  } catch(e:any){
    // Ne pas afficher d'erreur si c'est une erreur 401 (token expiré)
    if (e.response?.status !== 401) {
      snackbar.value = { show:true, text:e?.message || 'Erreur réseau', color:'error' }
    }
  } finally {
    loadingModifs.value = false
  }
}
</script>

<style scoped>
.pdf-wrapper { border:1px solid rgba(0,0,0,.12); border-radius:8px; overflow:hidden; }
.pdf-object { width:100%; height:70vh; }
</style>
