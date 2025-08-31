<template>
  <v-container class="py-6 mt-8" fluid>
    <v-card class="mx-auto" max-width="900">
      <v-card-title class="d-flex align-center justify-space-between">
        <span>Suivi campagne</span>
        <v-btn color="primary" :loading="loading" @click="init">Rafraîchir</v-btn>
      </v-card-title>
      <v-divider />

      <!-- Sélecteur de campagne -->
      <v-card-text>
        <v-select
          v-model="selectedCampagne"
          :items="campagnes"
          item-title="label"
          item-value="candidature_id"
          label="Sélectionner une campagne"
          variant="outlined"
          density="comfortable"
          :loading="loadingCampagnes"
          @update:model-value="onCampagneChange"
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props">
              <template #title>
                <div class="d-flex align-center justify-space-between">
                  <span>{{ item.raw.label }}</span>
                  <div class="d-flex align-center" style="gap: 0.5rem;">
                    <v-chip 
                      v-if="item.raw.paiement?.statut === 'paye'" 
                      size="small" 
                      color="success"
                    >
                      Payé
                    </v-chip>
                    <v-chip 
                      :color="getCampagneChipColor(item.raw.campagne?.statut)"
                      size="small"
                    >
                      {{ getCampagneStatusText(item.raw.campagne?.statut) }}
                    </v-chip>
                  </div>
                </div>
              </template>
            </v-list-item>
          </template>
        </v-select>
      </v-card-text>

      <v-divider />

      <v-card-text>
        <v-alert v-if="!selectedCampagne" type="info" variant="tonal">
          Veuillez sélectionner une campagne pour voir son suivi.
        </v-alert>
        <v-alert v-else-if="!contrat" type="info" variant="tonal">Contrat introuvable.</v-alert>

        <template v-else>
          <div class="d-flex flex-wrap" style="gap:.5rem">
            <v-chip :color="getCampagneChipColor(campagneStatus)">
              {{ getCampagneStatusText(campagneStatus) }}
            </v-chip>
            <v-chip v-if="suivi?.suivi_next_due_at && campagneStatus === 'active'" variant="tonal">
              Prochaine vidéo due : {{ formatDate(suivi.suivi_next_due_at) }}
              <span v-if="daysUntilNextDue !== null" class="ml-2">
                ({{ daysUntilNextDue > 0 ? `${daysUntilNextDue} jours` : 'Aujourd\'hui' }})
              </span>
            </v-chip>
            <v-chip v-if="campagneStatus === 'active' && isInUploadWindow && daysUntilWindowEnd !== null" variant="tonal" color="warning">
              {{ daysUntilWindowEnd > 0 ? `${daysUntilWindowEnd} jours restants` : 'Dernier jour' }}
            </v-chip>
          </div>

          <!-- Message d'état de la campagne -->
          <v-alert 
            v-if="campagneStatus === 'en_attente_validation_pose'" 
            type="warning" 
            variant="tonal"
            class="mt-4"
          >
            <strong>Vidéo de pose en attente de validation</strong><br>
            Votre vidéo de pose a été envoyée et est en cours de validation par l'annonceur. 
            La campagne sera activée une fois la vidéo validée.
          </v-alert>

          <v-alert 
            v-if="campagneStatus === 'en_attente_video_pose'" 
            type="info" 
            variant="tonal"
            class="mt-4"
          >
            <strong>Vidéo de pose requise</strong><br>
            Vous devez d'abord envoyer une vidéo de pose pour activer la campagne.
          </v-alert>

          <!-- Vidéos de pose -->
          <v-card class="mt-4" variant="outlined">
            <v-card-title class="text-subtitle-1">Vidéos de pose</v-card-title>
            <v-card-text>
              <!-- Liste des vidéos de pose existantes -->
              <div v-if="poseVideos.length > 0">
                <v-list>
                  <v-list-item
                    v-for="video in poseVideos"
                    :key="video.id"
                    :title="`Vidéo #${video.id}`"
                    :subtitle="`Envoyée le ${formatDate(video.created_at)}`"
                  >
                    <template #append>
                      <div class="d-flex align-center" style="gap: 0.5rem;">
                        <v-btn
                          color="primary"
                          size="small"
                          :href="fileHref(video.url)"
                          target="_blank"
                          rel="noopener"
                          icon="mdi-play-circle"
                          :title="'Ouvrir la vidéo'"
                        />
                        <v-chip 
                          :color="video.statut === 'valide' ? 'success' : (video.statut === 'en_attente' ? 'warning' : 'error')"
                          size="small"
                        >
                          {{ video.statut }}
                        </v-chip>
                      </div>
                    </template>
                  </v-list-item>
                </v-list>
              </div>
              
              <v-alert v-else-if="selectedCampagne" type="info" variant="tonal">
                Aucune vidéo de pose déposée pour le moment.
              </v-alert>

              <!-- Upload vidéo de pose - masqué si une vidéo est validée -->
              <v-form v-if="!hasValidatedPoseVideo" class="mt-4">
                <v-file-input
                  v-model="selectedPoseVideo"
                  accept="video/*"
                  :multiple="false"
                  label="Choisir une vidéo de pose (mp4, mov, avi, wmv)"
                  prepend-icon="mdi-video"
                  variant="outlined"
                  hint="Taille maximum : 200MB"
                  persistent-hint
                />

                <v-progress-linear
                  v-if="uploadProgress > 0 && uploadProgress < 100"
                  :model-value="uploadProgress"
                  color="primary"
                  height="8"
                  class="mt-3"
                  rounded
                >
                  <template #default="{ value }">
                    <strong>{{ Math.ceil(value) }}%</strong>
                  </template>
                </v-progress-linear>

                <v-btn
                  class="mt-3"
                  color="success"
                  :loading="uploadingPose"
                  :disabled="!selectedPoseVideo || !selectedCampagne"
                  @click="uploadPoseVideo"
                  prepend-icon="mdi-upload"
                >
                  {{ uploadingPose ? 'Envoi en cours...' : 'Envoyer la vidéo de pose' }}
                </v-btn>
              </v-form>
            </v-card-text>
          </v-card>

          <!-- Upload vidéo mensuelle -->
          <v-card class="mt-4" variant="outlined">
            <v-card-title class="text-subtitle-1">Vidéo mensuelle</v-card-title>
            <v-card-text>
              <v-alert v-if="campagneStatus !== 'active'" type="warning" variant="tonal">
                <div v-if="campagneStatus === 'en_attente_validation_pose'">
                  La campagne n'est pas encore active. Attendez la validation de la vidéo de pose par l'annonceur.
                </div>
                <div v-else-if="campagneStatus === 'en_attente_video_pose'">
                  La campagne n'est pas encore active. Vous devez d'abord envoyer une vidéo de pose.
                </div>
                <div v-else>
                  La campagne n'est pas encore active.
                </div>
              </v-alert>

              <v-alert v-if="campagneStatus === 'active' && !isInUploadWindow" type="info" variant="tonal">
                <strong>Fenêtre d'upload fermée</strong><br>
                <div v-if="suivi?.suivi_next_due_at">
                  Vous pouvez envoyer votre vidéo mensuelle du {{ windowStart ? formatDate(windowStart.toISOString()) : 'Date non définie' }} au {{ windowEnd ? formatDate(windowEnd.toISOString()) : 'Date non définie' }}.
                  <br>Prochaine échéance : {{ formatDate(suivi.suivi_next_due_at) }}
                </div>
                <div v-else>
                  Prochaine échéance non définie.
                  <br><small>Si votre vidéo de pose vient d'être validée, cliquez sur "Rafraîchir" pour mettre à jour les données.</small>
                </div>
              </v-alert>

              <v-alert v-if="campagneStatus === 'active' && isInUploadWindow" type="success" variant="tonal">
                <strong>Fenêtre d'upload ouverte</strong><br>
                Vous pouvez envoyer votre vidéo mensuelle jusqu'au {{ windowEnd ? formatDate(windowEnd.toISOString()) : 'Date non définie' }}.
              </v-alert>

              <div class="d-flex flex-column" style="gap:.75rem">
                <v-file-input
                  v-model="selectedMonthly"
                  accept="video/*"
                  :multiple="false"
                  label="Choisir une vidéo (mp4)"
                  prepend-icon="mdi-video"
                  variant="outlined"
                  :disabled="!canUploadMonthly"
                  hint="Taille maximum : 200MB"
                  persistent-hint
                />

                <v-progress-linear
                  v-if="uploadProgressMonthly > 0 && uploadProgressMonthly < 100"
                  :model-value="uploadProgressMonthly"
                  color="primary"
                  height="8"
                  class="mt-3"
                  rounded
                >
                  <template #default="{ value }">
                    <strong>{{ Math.ceil(value) }}%</strong>
                  </template>
                </v-progress-linear>

                <v-btn
                  color="success"
                  :loading="uploadingMonthly"
                  :disabled="!canUploadMonthly"
                  @click="uploadMonthly"
                  prepend-icon="mdi-upload"
                >
                  {{ uploadingMonthly ? 'Envoi en cours...' : 'Envoyer la vidéo du mois' }}
                </v-btn>
              </div>
            </v-card-text>
          </v-card>

          <!-- Historique validations -->
          <v-card class="mt-4" variant="outlined">
            <v-card-title class="text-subtitle-1">Historique validations</v-card-title>
            <v-card-text>
              <v-alert v-if="historique.length===0" type="info" variant="tonal">
                Aucune validation mensuelle pour l'instant.
              </v-alert>

              <v-list v-else>
                <v-list-item
                  v-for="h in historique"
                  :key="h.id"
                  :title="`Mois ${h.mois} — ${formatDate(h.due_at || h.created_at)}`"
                  :subtitle="`Statut : ${h.statut}`"
                >
                  <template #append>
                    <v-btn
                      v-if="h.video_url"
                      size="small"
                      color="primary"
                      :href="fileHref(h.video_url)"
                      target="_blank"
                      rel="noopener"
                      icon="mdi-play-circle"
                      :title="'Ouvrir la vidéo'"
                    />
                    <v-chip class="ml-2" :color="chipColor(h.statut)">{{ h.statut }}</v-chip>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>

          <!-- Historique paiements mensuels -->
          <v-card class="mt-4" variant="outlined">
            <v-card-title class="text-subtitle-1">Paiements mensuels</v-card-title>
            <v-card-text>
              <v-alert v-if="paiements.length===0" type="info" variant="tonal">
                Aucun paiement mensuel pour l'instant.
              </v-alert>

              <v-list v-else>
                <v-list-item
                  v-for="p in paiements"
                  :key="p.id"
                  :title="`Paiement mensuel — ${formatDate(p.date_paiement)}`"
                  :subtitle="`Votre part : ${p.montant_particulier}€ (Commission POYOS : ${p.commission_poyos}€)`"
                >
                  <template #append>
                    <v-chip class="ml-2" :color="chipColor(p.statut)">{{ p.statut }}</v-chip>
                  </template>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </template>
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snack.show" :timeout="3000" :color="snack.color">
      {{ snack.text }}
    </v-snackbar>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

const API = 'http://localhost:8080'

const cid = ref<string | number | null>(null)
const selectedCampagne = ref<number|null>(null)
const campagnes = ref<any[]>([])
const contrat = ref<any|null>(null)
const suivi  = ref<any|null>(null)
const historique = ref<any[]>([])
const paiements = ref<any[]>([])
const poseVideos = ref<any[]>([])
const loading = ref(false)
const loadingCampagnes = ref(false)
const uploadingPose = ref(false)
const selectedPoseVideo = ref<File|File[]|null>(null)
const selectedMonthly = ref<File|File[]|null>(null)
const uploadProgress = ref(0)
const uploadProgressMonthly = ref(0)
const uploadingMonthly = ref(false)
const snack = ref({ show:false, text:'', color:'success' })

// Computed pour le statut de la campagne
const campagneStatus = computed(() => {
  if (!selectedCampagne.value) return null
  const campagne = campagnes.value.find(c => c.candidature_id === selectedCampagne.value)
  return campagne?.campagne?.statut || 'en_attente'
})

// Computed pour vérifier si une vidéo de pose est validée
const hasValidatedPoseVideo = computed(() => {
  return poseVideos.value.some(video => video.statut === 'valide')
})

// Computed pour la fenêtre d'upload mensuel
const windowStart = computed(() => {
  if (!suivi.value?.suivi_next_due_at) return null
  const dueDate = new Date(suivi.value.suivi_next_due_at)
  const start = new Date(dueDate)
  start.setDate(start.getDate() - 2)
  return start
})

const windowEnd = computed(() => {
  if (!suivi.value?.suivi_next_due_at) return null
  const dueDate = new Date(suivi.value.suivi_next_due_at)
  const end = new Date(dueDate)
  end.setDate(end.getDate() + 5)
  return end
})

const isInUploadWindow = computed(() => {
  if (!windowStart.value || !windowEnd.value) return false
  const now = new Date()
  return now >= windowStart.value && now <= windowEnd.value
})

const canUploadMonthly = computed(() => {
  return campagneStatus.value === 'active' && 
         isInUploadWindow.value && 
         selectedMonthly.value && 
         !uploadingMonthly.value
})

// Computed pour calculer les jours restants
const daysUntilNextDue = computed(() => {
  if (!suivi.value?.suivi_next_due_at) return null
  const now = new Date()
  const dueDate = new Date(suivi.value.suivi_next_due_at)
  const diffTime = dueDate.getTime() - now.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays
})

const daysUntilWindowEnd = computed(() => {
  if (!windowEnd.value) return null
  const now = new Date()
  const diffTime = windowEnd.value.getTime() - now.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return Math.max(0, diffDays)
})

function fileHref(u:string){ return /^https?:\/\//i.test(u) ? u : `${API}/${String(u).replace(/^\/+/, '')}` }
function formatDate(d:string){ 
  if (!d) return 'Date non définie'
  try{ 
    const date = new Date(d)
    if (isNaN(date.getTime())) return 'Date invalide'
    return date.toLocaleString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  }catch{ 
    return 'Date invalide'
  } 
}
function chipColor(s:string){ return s==='valide' ? 'success' : (s==='en_attente' ? 'warning' : (s==='refuse' ? 'error' : 'default')) }

function getCampagneChipColor(status: string) {
  switch (status) {
    case 'active': return 'success'
    case 'en_attente_validation_pose': return 'warning'
    case 'en_attente_video_pose': return 'info'
    default: return 'grey'
  }
}

function getCampagneStatusText(status: string) {
  switch (status) {
    case 'active': return 'Campagne active'
    case 'en_attente_validation_pose': return 'En attente validation pose'
    case 'en_attente_video_pose': return 'Vidéo pose requise'
    default: return 'En attente'
  }
}

async function loadCampagnes(){
  try{
    loadingCampagnes.value = true
    const { data } = await axios.get(`${API}/annonces/get_campagnes_conducteur.php`, {
      headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` }
    })
    if(!data?.success) throw new Error(data?.message||'Erreur chargement campagnes')
    
    campagnes.value = data.data.campagnes.map((c: any) => ({
      candidature_id: c.candidature_id,
      label: `${c.titre_annonce} - ${c.annonceur.nom_entreprise || c.annonceur.prenom} ${c.annonceur.nom}`,
      ...c
    }))
  }catch(e:any){
    snack.value = { show:true, text:e?.message||'Erreur campagnes', color:'error' }
  }finally{ loadingCampagnes.value = false }
}

async function loadPoseVideos(){
  if(!selectedCampagne.value) return
  try{
    const { data } = await axios.get(`${API}/videos/get_pose_videos.php`, {
      params:{ candidature_id: selectedCampagne.value },
      headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` }
    })
    if(data?.success) poseVideos.value = data.data || []
  }catch(e:any){
    snack.value = { show:true, text:'Erreur chargement vidéos de pose', color:'error' }
  }
}

function onCampagneChange(candidatureIdParam: number){
  cid.value = candidatureIdParam
  selectedCampagne.value = candidatureIdParam
  if(candidatureIdParam) init()
}

async function loadContrat(){
  const { data } = await axios.get(`${API}/contrats/get_contrat.php`, {
    params:{ candidature_id: cid.value },
    headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` }
  })
  if(!data?.success) throw new Error(data?.message||'Erreur contrat')
  contrat.value = data.data || null
}

async function loadSuivi(){
  if(!contrat.value?.id) return
  const { data } = await axios.get(`${API}/annonces/get_suivi.php`, {
    params:{ contrat_id: contrat.value.id },
    headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` }
  })
  if(!data?.success) throw new Error(data?.message||'Erreur suivi')
  suivi.value = data.data?.suivi || null
  historique.value = data.data?.historique || []
  paiements.value = data.data?.paiements || []
}

async function uploadPoseVideo(){
  if(!selectedPoseVideo.value || !selectedCampagne.value) return
  try{
    uploadingPose.value = true
    uploadProgress.value = 0

    const file = Array.isArray(selectedPoseVideo.value) ? selectedPoseVideo.value[0] : selectedPoseVideo.value
    if(!file) throw new Error("Aucun fichier sélectionné")

    // Vérifier la taille du fichier (limite à 200MB)
    const maxSize = 200 * 1024 * 1024 // 200MB
    if (file.size > maxSize) {
      throw new Error(`Fichier trop volumineux (${(file.size / 1024 / 1024).toFixed(1)}MB). Maximum : 200MB. Veuillez compresser votre vidéo.`)
    }

    const form = new FormData()
    form.append('candidature_id', String(selectedCampagne.value))
    form.append('video', file)

    const { data } = await axios.post(`${API}/videos/upload_pose_video.php`, form, {
      headers:{ 
        Authorization:`Bearer ${localStorage.getItem('token')||''}`,
        'Content-Type':'multipart/form-data'
      },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          uploadProgress.value = (progressEvent.loaded / progressEvent.total) * 100
        }
      }
    })
    
    if(!data?.success) throw new Error(data?.message||'Erreur upload')
    uploadProgress.value = 100
    snack.value = { show:true, text:'Vidéo de pose envoyée ✅', color:'success' }
    selectedPoseVideo.value = null
    await Promise.all([loadPoseVideos(), loadCampagnes()]) // Recharger pour mettre à jour le statut
  }catch(e:any){
    snack.value = { show:true, text:e?.response?.data?.message || e?.message || 'Erreur envoi vidéo de pose', color:'error' }
  }finally{ 
    uploadingPose.value = false 
    uploadProgress.value = 0
  }
}

async function uploadMonthly(){
  if(!selectedMonthly.value || !contrat.value?.id) return
  try{
    uploadingMonthly.value = true
    uploadProgressMonthly.value = 0

    const file = Array.isArray(selectedMonthly.value) ? selectedMonthly.value[0] : selectedMonthly.value
    if(!file) throw new Error("Aucun fichier sélectionné")

    // Vérifier la taille du fichier (limite à 200MB)
    const maxSize = 200 * 1024 * 1024 // 200MB
    if (file.size > maxSize) {
      throw new Error(`Fichier trop volumineux (${(file.size / 1024 / 1024).toFixed(1)}MB). Maximum : 200MB. Veuillez compresser votre vidéo.`)
    }

    const form = new FormData()
    form.append('contrat_id', String(contrat.value.id))
    form.append('video', file)

    const { data } = await axios.post(`${API}/videos/upload_video_mensuelle.php`, form, {
      headers:{ 
        Authorization:`Bearer ${localStorage.getItem('token')||''}`,
        'Content-Type':'multipart/form-data'
      },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          uploadProgressMonthly.value = (progressEvent.loaded / progressEvent.total) * 100
        }
      }
    })
    
    if(!data?.success) throw new Error(data?.message||'Erreur upload')
    uploadProgressMonthly.value = 100
    snack.value = { show:true, text:'Vidéo mensuelle envoyée ✅', color:'success' }
    selectedMonthly.value = null
    await loadSuivi() // Recharger l'historique
  }catch(e:any){
    snack.value = { show:true, text:e?.response?.data?.message || e?.message || 'Erreur envoi vidéo mensuelle', color:'error' }
  }finally{ 
    uploadingMonthly.value = false 
    uploadProgressMonthly.value = 0
  }
}


async function init(){
  try{
    loading.value = true
    await loadContrat()
    if(contrat.value) {
      await Promise.all([loadSuivi(), loadPoseVideos()])
    }
  }catch(e:any){
    snack.value = { show:true, text:e?.message||'Erreur', color:'error' }
  }finally{ loading.value = false }
}

onMounted(async ()=>{
  await loadCampagnes()
  
  // Si on a un candidatureId dans l'URL, on le sélectionne
  const url = new URL(window.location.href)
  const q = url.searchParams.get('candidatureId')
  if(q){
    const candidatureId = Number(q)
    selectedCampagne.value = candidatureId
    cid.value = candidatureId
    await init()
  }
})
</script>
