<template>
  <v-container class="py-6 mt-8" fluid>
    <v-card class="mx-auto" max-width="1000">
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
                  <v-chip 
                    v-if="item.raw.paiement?.statut === 'paye'" 
                    size="small" 
                    color="success"
                  >
                    Payé
                  </v-chip>
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
            <v-chip :color="suivi?.suivi_active ? 'success':'grey'">
              {{ suivi?.suivi_active ? 'Campagne active' : 'En attente activation' }}
            </v-chip>
            <v-chip v-if="suivi?.suivi_next_due_at" variant="tonal">
              Prochaine due : {{ formatDate(suivi.suivi_next_due_at) }}
            </v-chip>
          </div>

          <!-- Vidéos de pose (initiales) -->
          <v-card class="mt-4" variant="outlined">
            <v-card-title class="text-subtitle-1">Vidéos de pose</v-card-title>
            <v-card-text>
              <v-alert v-if="poseVideos.length===0" type="info" variant="tonal">
                Aucune vidéo de pose envoyée pour le moment.
              </v-alert>

              <v-table v-else density="comfortable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="pv in poseVideos" :key="pv.id">
                    <td>{{ pv.id }}</td>
                    <td>{{ formatDate(pv.created_at) }}</td>
                    <td><v-chip :color="pv.statut==='valide'?'success':(pv.statut==='en_attente'?'warning':'error')">{{ pv.statut }}</v-chip></td>
                    <td class="d-flex" style="gap:.5rem">
                      <v-btn size="small" color="primary" :href="fileHref(pv.url)" target="_blank" icon="mdi-play-circle" />
                      <v-btn
                        size="small"
                        color="success"
                        prepend-icon="mdi-check"
                        :disabled="pv.statut!=='en_attente'"
                        :loading="actLoading && actId===pv.id"
                        @click="validerPose(pv.id)"
                      >Valider la pose</v-btn>
                    </td>
                  </tr>
                </tbody>
              </v-table>
            </v-card-text>
          </v-card>

          <!-- Historique mensuel -->
          <v-card class="mt-4" variant="outlined">
            <v-card-title class="text-subtitle-1">Validations mensuelles</v-card-title>
            <v-card-text>
              <v-alert v-if="historique.length===0" type="info" variant="tonal">
                Pas encore de dépôt mensuel.
              </v-alert>

              <v-table v-else density="comfortable">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Mois</th>
                    <th>Échéance</th>
                    <th>Statut</th>
                    <th>Vidéo</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="h in historique" :key="h.id">
                    <td>{{ h.id }}</td>
                    <td>{{ h.mois }}</td>
                    <td>{{ formatDate(h.due_at || h.created_at) }}</td>
                    <td><v-chip :color="chipColor(h.statut)">{{ h.statut }}</v-chip></td>
                    <td>
                      <v-btn
                        v-if="h.video_url"
                        size="small"
                        color="primary"
                        :href="fileHref(h.video_url)"
                        target="_blank"
                        icon="mdi-play-circle"
                      />
                    </td>
                    <td>
                      <v-btn
                        size="small"
                        color="success"
                        prepend-icon="mdi-check"
                        :disabled="h.statut!=='en_attente'"
                        :loading="actLoading && actId===h.id"
                        @click="validerMensuelle(h.id)"
                      >
                        Valider
                      </v-btn>
                    </td>
                  </tr>
                </tbody>
              </v-table>
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
import { ref, onMounted } from 'vue'
import axios from 'axios'

const API = 'http://localhost:8080'

const cid = ref<string|number|null>(null)
const selectedCampagne = ref<number|null>(null)
const campagnes = ref<any[]>([])
const contrat = ref<any|null>(null)
const suivi = ref<any|null>(null)
const historique = ref<any[]>([])
const poseVideos = ref<any[]>([])

const loading = ref(false)
const loadingCampagnes = ref(false)
const actLoading = ref(false)
const actId = ref<number|null>(null)
const snack = ref({ show:false, text:'', color:'success' })

function fileHref(u:string){ return /^https?:\/\//i.test(u) ? u : `${API}/${String(u).replace(/^\/+/, '')}` }
function formatDate(d:string){ try{ return new Date(d).toLocaleString() }catch{ return d } }
function chipColor(s:string){ return s==='valide' ? 'success' : (s==='en_attente' ? 'warning' : (s==='refuse' ? 'error' : 'default')) }

async function loadCampagnes(){
  try{
    loadingCampagnes.value = true
    const { data } = await axios.get(`${API}/annonces/get_campagnes_annonceur.php`, {
      headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` }
    })
    if(!data?.success) throw new Error(data?.message||'Erreur chargement campagnes')
    
    campagnes.value = data.data.campagnes.map((c: any) => ({
      candidature_id: c.candidature_id,
      label: `${c.titre_annonce} - ${c.conducteur.prenom} ${c.conducteur.nom}`,
      ...c
    }))
  }catch(e:any){
    snack.value = { show:true, text:e?.message||'Erreur campagnes', color:'error' }
  }finally{ loadingCampagnes.value = false }
}

function onCampagneChange(candidatureId: number){
  cid.value = candidatureId
  if(candidatureId) init()
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
}

async function loadPoseVideos(){
  const { data } = await axios.get(`${API}/videos/get_pose_videos.php`, {
    params:{ candidature_id: cid.value },
    headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` }
  })
  if(data?.success) poseVideos.value = data.data || []
}

async function validerPose(poseVideoId:number){
  try{
    actLoading.value = true; actId.value = poseVideoId
    const { data } = await axios.post(`${API}/videos/valider_pose_video.php`,
      { candidature_id: Number(cid.value), pose_video_id: poseVideoId },
      { headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` } }
    )
    if(!data?.success) throw new Error(data?.message||'Erreur validation pose')
    snack.value = { show:true, text:'Pose validée — campagne activée ✅', color:'success' }
    await Promise.all([loadSuivi(), loadPoseVideos()])
  }catch(e:any){
    snack.value = { show:true, text:e?.message||'Erreur validation', color:'error' }
  }finally{ actLoading.value=false; actId.value=null }
}

async function validerMensuelle(validationId:number){
  try{
    actLoading.value = true; actId.value = validationId
    const { data } = await axios.post(`${API}/videos/valider_video_mensuelle.php`,
      { validation_id: validationId },
      { headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` } }
    )
    if(!data?.success) throw new Error(data?.message||'Erreur validation mensuelle')
    snack.value = { show:true, text:'Validation mensuelle confirmée ✅', color:'success' }
    await loadSuivi()
  }catch(e:any){
    snack.value = { show:true, text:e?.message||'Erreur validation', color:'error' }
  }finally{ actLoading.value=false; actId.value=null }
}

async function init(){
  try{
    loading.value = true
    await loadContrat()
    if(contrat.value){
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
