<template>
  <v-container class="py-6 mt-8" fluid>
    <v-card class="mx-auto" max-width="900">
      <v-card-title class="d-flex align-center justify-space-between">
        <span>Contrat — Candidature {{ cid || '—' }}</span>
        <div class="d-flex align-center" style="gap:.5rem">
          <v-text-field v-model="cid" label="ID candidature" variant="outlined" density="comfortable" style="max-width:220px" hide-details="auto"/>
          <v-btn color="primary" :loading="loading" @click="init">Charger</v-btn>
        </div>
      </v-card-title>
      <v-divider/>

      <v-card-text>
        <v-alert v-if="!contrat && !loading" type="info" variant="tonal">Aucun contrat trouvé.</v-alert>

        <div class="d-flex flex-wrap" style="gap:.5rem" v-if="contrat">
          <v-chip :color="contrat.statut_contrat==='signe' ? 'success' : 'info'" variant="flat">
            {{ contrat.statut_contrat==='signe' ? 'Contrat signé' : 'En signature' }}
          </v-chip>
          <v-chip variant="tonal" :color="contrat.signature_annonceur_at ? 'success' : 'grey'">
            Annonceur {{ contrat.signature_annonceur_at ? 'signé' : '—' }}
          </v-chip>
          <v-chip variant="tonal" :color="contrat.signature_conducteur_at ? 'success' : 'grey'">
            Conducteur {{ contrat.signature_conducteur_at ? 'signé' : '—' }}
          </v-chip>
        </div>

        <v-card variant="outlined" class="mt-4" v-if="contrat">
          <v-card-title class="text-subtitle-1">Contrat</v-card-title>
          <v-card-text>
            <v-btn v-if="contrat?.contrat_pdf_url" :href="fileHref(contrat.contrat_pdf_url)" target="_blank" rel="noopener" prepend-icon="mdi-file-pdf-box">
              Ouvrir le document
            </v-btn>
            <div class="mt-4">
              <v-btn
                color="success"
                :disabled="!!contrat.signature_conducteur_at"
                :loading="loadingSign"
                @click="signer"
                prepend-icon="mdi-check-decagram"
              >
                Signer le contrat (conducteur)
              </v-btn>
            </div>
          </v-card-text>
        </v-card>

        <!-- Paiement -->
        <v-card v-if="contrat?.statut_contrat==='signe'" variant="outlined" class="mt-6">
          <v-card-title class="text-subtitle-1">Paiement</v-card-title>
          <v-card-text>
            <div v-if="paiement">
              <p><b>Montant total :</b> {{ paiement.montant_total }} €</p>
              <p><b>Commission Poyos :</b> {{ paiement.commission_poyos }} €</p>
              <p><b>Montant net :</b> {{ paiement.montant_particulier }} €</p>
              <v-chip :color="paiement.statut==='paye' ? 'success' : 'warning'">
                {{ paiement.statut === 'paye' ? 'Payé' : 'En attente de paiement' }}
              </v-chip>
            </div>
            <v-alert v-else type="info" variant="tonal">
              Aucun paiement créé pour le moment.
            </v-alert>
          </v-card-text>
        </v-card>
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

const API = 'http://localhost:8080'
const cid = ref<string | number | null>(null)
const loading = ref(false)
const loadingSign = ref(false)
const contrat = ref<any|null>(null)
const paiement = ref<any|null>(null)
const snackbar = ref({ show:false, text:'', color:'success' })

function fileHref(u:string){
  if(/^https?:\/\//i.test(u)) return u
  return `${API}/${String(u).replace(/^\/+/, '')}`
}

async function loadContrat(){
  if(!cid.value) return
  const { data } = await axios.get(`${API}/contrats/get_contrat.php`, {
    params:{ candidature_id: cid.value },
    headers:{ Authorization:`Bearer ${localStorage.getItem('token') || ''}` }
  })
  if(!data?.success) throw new Error(data?.message || 'Erreur chargement contrat')
  contrat.value = data.data || null
  if (contrat.value?.id) await loadPaiement()
}

async function loadPaiement(){
  const { data } = await axios.get(`${API}/paiements/get_paiement.php`, {
    params: { contrat_id: contrat.value.id },
    headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` },
  })
  if (data?.success) paiement.value = data.data
}

async function signer(){
  if(!cid.value) return
  try{
    loadingSign.value = true
    const { data } = await axios.post(`${API}/contrats/signer_contrat.php`,
      { candidature_id:Number(cid.value) },
      { headers:{ Authorization:`Bearer ${localStorage.getItem('token') || ''}` } }
    )
    if(!data?.success) throw new Error(data?.message || 'Erreur signature')
    snackbar.value = { show:true, text:'Contrat signé (conducteur)', color:'success' }
    await loadContrat()
  }catch(e:any){
    snackbar.value = { show:true, text:e?.message || 'Erreur', color:'error' }
  }finally{ loadingSign.value=false }
}

async function init(){
  try{
    loading.value=true
    await loadContrat()
  }catch(e:any){
    snackbar.value = { show:true, text:e?.message || 'Erreur', color:'error' }
  }finally{ loading.value=false }
}

onMounted(()=>{
  const url=new URL(window.location.href)
  const q=url.searchParams.get('candidatureId')
  if(q){ cid.value=q; init() }
})
</script>
