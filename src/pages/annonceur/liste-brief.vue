<template>
  <v-container class="py-6" fluid>
    <v-card class="mx-auto" max-width="1200">
      <!-- Header + charge -->
      <v-card-title class="text-h6 d-flex align-center justify-space-between">
        <span>Brief & Validation BAT — Candidature {{ cid || '—' }}</span>
        <div class="d-flex align-center" style="gap:.5rem">
          <v-text-field
            v-model="cid"
            label="ID candidature"
            variant="outlined"
            density="comfortable"
            style="max-width:220px"
            hide-details="auto"
          />
          <v-btn color="primary" :loading="loading" @click="loadAll">Charger</v-btn>
        </div>
      </v-card-title>
      <v-divider />

      <v-card-text>
        <v-row dense class="mt-2">
          <!-- Infos brief + statut -->
          <v-col cols="12" md="5">
            <v-card variant="outlined" class="mb-4">
              <v-card-title class="text-subtitle-1">Informations brief</v-card-title>
              <v-card-text>
                <v-alert v-if="!brief && !loading" type="info" variant="tonal">Aucun brief.</v-alert>

                <template v-if="brief">
                  <div class="mb-2"><b>Créé le :</b> {{ fmt(brief.created_at) }}</div>
                  <div class="mb-2"><b>Contraintes :</b> {{ brief.champs_json?.contraintes || '—' }}</div>
                  <div class="mb-2">
                    <b>Zones :</b>
                    <template v-if="(brief.champs_json?.zones || []).length">
                      <v-chip
                        v-for="(z,i) in brief.champs_json.zones" :key="i"
                        size="small" class="ma-1" color="primary" variant="tonal"
                      >{{ z }}</v-chip>
                    </template>
                    <span v-else>—</span>
                  </div>
                  <div><b>Commentaires :</b> {{ brief.champs_json?.commentaires || '—' }}</div>
                </template>
              </v-card-text>
            </v-card>

            <v-card variant="outlined">
              <v-card-title class="text-subtitle-1">Statut & validations</v-card-title>
              <v-card-text>
                <div v-if="status" class="d-flex flex-wrap" style="gap:.4rem">
                  <v-chip :color="statusColor" variant="flat">{{ statusLabel }}</v-chip>

                  <!-- Annonceur : vert quand validé -->
                  <v-chip :color="status.valide_annonceur === true ? 'success' : 'grey'"
                          :variant="status.valide_annonceur === true ? 'flat' : 'tonal'">
                    Annonceur {{ status.valide_annonceur === true ? 'validé' : '—' }}
                  </v-chip>

                  <v-chip :color="status.valide_conducteur === true ? 'success' : 'grey'"
                          :variant="status.valide_conducteur === true ? 'flat' : 'tonal'">
                    Conducteur {{ status.valide_conducteur === true ? 'validé' : '—' }}
                  </v-chip>

                  <v-chip v-if="lastAsset" variant="tonal">
                    Dernier asset : {{ lastAsset.type.toUpperCase() }} v{{ lastAsset.version }}
                  </v-chip>
                </div>

                <v-alert
                  v-if="status?.statut === 'pret_a_commander'"
                  type="success" variant="tonal" class="mt-3"
                >
                  Validations complètes. Étape suivante : <b>Planifier la pose</b>.
                </v-alert>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Aperçu BAT + actions -->
          <v-col cols="12" md="7">
            <v-card variant="outlined" class="mb-4">
              <v-card-title class="text-subtitle-1 d-flex align-center justify-space-between">
                <span>Aperçu BAT</span>
                <v-btn v-if="batHref" :href="batHref" target="_blank" rel="noopener" variant="text" prepend-icon="mdi-open-in-new">
                  Ouvrir dans un onglet
                </v-btn>
              </v-card-title>
              <v-card-text>
                <v-alert v-if="!batHref && !loading" type="warning" variant="tonal">Aucun BAT disponible.</v-alert>
                <v-alert v-if="pdfTooLarge" type="warning" variant="tonal" class="mb-3">
                  PDF volumineux : privilégie l’ouverture dans un onglet.
                </v-alert>
                <div v-if="batHref" class="pdf-wrapper">
                  <object :data="batHref" type="application/pdf" class="pdf-object">
                    <a :href="batHref" target="_blank" rel="noopener">Voir le BAT</a>
                  </object>
                </div>
              </v-card-text>
            </v-card>

            <!-- Section actions cachée si annonceur a déjà validé -->
            <v-card v-if="batHref && status && status.valide_annonceur === false" variant="outlined">
              <v-card-title class="text-subtitle-1">Actions</v-card-title>
              <v-card-text class="d-flex flex-wrap" style="gap:.5rem">
                <v-btn color="success" :loading="loadingValidate" @click="validerBAT" prepend-icon="mdi-check-circle">
                  Valider le BAT
                </v-btn>
                <v-btn color="warning" variant="outlined" @click="dialog=true" prepend-icon="mdi-comment-edit-outline">
                  Demander des modifications
                </v-btn>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Demande de modifs -->
    <v-dialog v-model="dialog" max-width="600">
      <v-card>
        <v-card-title class="text-h6">Demander des modifications</v-card-title>
        <v-card-text>
          <v-textarea v-model="commentaire" label="Commentaire" variant="outlined" rows="4" auto-grow />
        </v-card-text>
        <v-card-actions class="justify-end">
          <v-btn variant="text" @click="dialog=false">Annuler</v-btn>
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

/* ====== Config simple ====== */
const API = 'http://localhost:8080'

/* ====== State ====== */
const cid = ref<string | number | null>(null)
const loading = ref(false)
const loadingValidate = ref(false)
const loadingModifs = ref(false)
const brief = ref<any | null>(null)
const assets = ref<any[]>([])
const status = ref<{ statut:string, valide_annonceur:boolean, valide_conducteur:boolean } | null>(null)

const dialog = ref(false)
const commentaire = ref('')
const snackbar = ref({ show:false, text:'', color:'success' })

/* ====== Helpers simples ====== */
const fmt = (iso:string) => { try { return new Date(iso).toLocaleString() } catch { return iso } }
const token = () => localStorage.getItem('token') || ''

/* Dernier BAT + dernier asset */
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

/* Alerte PDF lourd */
const pdfTooLarge = computed(() => {
  const u = String(batAsset.value?.url || '')
  if (!u.startsWith('data:')) return false
  const base64 = (u.split(',')[1] || '').replace(/=+$/,'')
  const size = Math.floor(base64.length * 3 / 4)
  return size > 15 * 1024 * 1024
})

/* Labels & couleurs statut */
const statusLabel = computed(() => {
  switch (status.value?.statut) {
    case 'en_brief': return 'En brief'
    case 'maquette_en_cours': return 'Maquette en cours'
    case 'bat_disponible': return 'BAT disponible'
    case 'bat_modifs': return 'Modifs demandées'
    case 'pret_a_commander': return 'Prêt à commander'
    case 'pose_planifiee': return 'Pose planifiée'
    case 'pose_effectuee': return 'Pose effectuée'
    case 'campagne_active': return 'Campagne active'
    default: return status.value?.statut || '—'
  }
})
const statusColor = computed(() => {
  switch (status.value?.statut) {
    case 'bat_modifs': return 'warning'
    case 'pret_a_commander': return 'success'
    case 'campagne_active': return 'success'
    case 'bat_disponible': return 'info'
    case 'maquette_en_cours': return 'primary'
    case 'en_brief': return 'grey'
    default: return 'grey'
  }
})

/* ====== Actions ====== */
async function loadAll() {
  if (!cid.value) return
  try {
    loading.value = true
    const headers = { Authorization: `Bearer ${token()}` }

    const [bRes, aRes] = await Promise.all([
      axios.get(`${API}/briefs/get_brief.php`, { params:{ candidature_id: cid.value }, headers }),
      axios.get(`${API}/briefs/lister_design_assets.php`, { params:{ candidature_id: cid.value }, headers }),
    ])
    if (!bRes.data?.success) throw new Error(bRes.data?.message || 'Erreur brief')
    if (!aRes.data?.success) throw new Error(aRes.data?.message || 'Erreur assets')
    brief.value  = bRes.data.data?.brief || null
    assets.value = aRes.data.data?.assets || []

    const sRes = await axios.get(`${API}/briefs/get_design_status.php`, { params:{ candidature_id: cid.value }, headers })
    if (!sRes.data?.success) throw new Error(sRes.data?.message || 'Erreur statut')
    status.value = {
      statut: sRes.data.data?.statut,
      valide_annonceur: Boolean(sRes.data.data?.valide_annonceur),
      valide_conducteur: Boolean(sRes.data.data?.valide_conducteur),
    }
  } catch (e:any) {
    snackbar.value = { show:true, text: e?.message || 'Erreur réseau', color:'error' }
  } finally { loading.value = false }
}

async function validerBAT() {
  if (!cid.value) return
  try {
    loadingValidate.value = true
    const { data } = await axios.post(`${API}/briefs/valider_bat.php`,
      { candidature_id: Number(cid.value) },
      { headers:{ Authorization: `Bearer ${token()}` } }
    )
    if (!data?.success) throw new Error(data?.message || 'Échec validation')
    snackbar.value = { show:true, text:'Validation enregistrée', color:'success' }
    await loadAll()
  } catch (e:any) {
    snackbar.value = { show:true, text: e?.message || 'Erreur réseau', color:'error' }
  } finally { loadingValidate.value = false }
}

async function demanderModifs() {
  if (!cid.value) return
  try {
    loadingModifs.value = true
    const { data } = await axios.post(`${API}/briefs/demander_modifs_bat.php`,
      { candidature_id: Number(cid.value), commentaire: commentaire.value || '' },
      { headers:{ Authorization: `Bearer ${token()}` } }
    )
    if (!data?.success) throw new Error(data?.message || 'Échec demande')
    snackbar.value = { show:true, text:'Demande envoyée', color:'warning' }
    commentaire.value = ''
    dialog.value = false
    await loadAll()
  } catch (e:any) {
    snackbar.value = { show:true, text: e?.message || 'Erreur réseau', color:'error' }
  } finally { loadingModifs.value = false }
}

/* Auto-préremplir ID depuis l’URL */
onMounted(() => {
  const url = new URL(window.location.href)
  const q = url.searchParams.get('candidatureId')
  if (q) { cid.value = q; loadAll() }
})
</script>

<style scoped>
.pdf-wrapper { border: 1px solid rgba(0,0,0,.12); border-radius: 8px; overflow: hidden; }
.pdf-object  { width: 100%; height: 70vh; }
</style>
