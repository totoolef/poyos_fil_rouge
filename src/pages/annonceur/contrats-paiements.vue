<template>
  <v-container class="py-6 mt-8" fluid>
    <v-card class="mx-auto" max-width="900">
      <v-card-title class="d-flex align-center justify-space-between">
        <span>Contrats & Paiements — Candidature {{ cid || '—' }}</span>
        <div class="d-flex align-center" style="gap:.5rem">
          <v-text-field
            v-model="cid"
            label="ID candidature"
            variant="outlined"
            density="comfortable"
            style="max-width:220px"
            hide-details="auto"
          />
          <v-btn color="primary" :loading="loading" @click="init">Charger</v-btn>
        </div>
      </v-card-title>
      <v-divider />

      <v-card-text>
        <!-- Infos contrat -->
        <v-alert v-if="!contrat && !loading" type="info" variant="tonal">
          Aucun contrat. Cliquez “Générer le contrat”.
        </v-alert>

        <div v-if="contrat" class="d-flex flex-wrap" style="gap:.5rem">
          <v-chip :color="contrat.statut_contrat==='signe' ? 'success' : 'info'" variant="flat">
            {{ contrat.statut_contrat==='signe' ? 'Contrat signé' : 'En signature' }}
          </v-chip>
          <v-chip :color="contrat.signature_annonceur_at ? 'success' : 'grey'" variant="tonal">
            Annonceur {{ contrat.signature_annonceur_at ? 'signé' : '—' }}
          </v-chip>
          <v-chip :color="contrat.signature_conducteur_at ? 'success' : 'grey'" variant="tonal">
            Conducteur {{ contrat.signature_conducteur_at ? 'signé' : '—' }}
          </v-chip>
        </div>

        <!-- Contrat -->
        <v-card variant="outlined" class="mt-4">
          <v-card-title class="text-subtitle-1">Contrat</v-card-title>
          <v-card-text>
            <v-btn
              v-if="contrat?.contrat_pdf_url"
              :href="fileHref(contrat.contrat_pdf_url)"
              target="_blank"
              rel="noopener"
              prepend-icon="mdi-file-pdf-box"
            >
              Ouvrir le document
            </v-btn>
            <div class="mt-4 d-flex" style="gap:.5rem">
              <v-btn color="primary" :loading="loadingCreate" @click="creerContrat" prepend-icon="mdi-file-plus">
                Générer le contrat
              </v-btn>
              <v-btn color="success" :disabled="!contrat" :loading="loadingSign" @click="signer" prepend-icon="mdi-check-decagram">
                Signer le contrat (annonceur)
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
                {{ paiement.statut }}
              </v-chip>

              <!-- Bouton Stripe quand en attente -->
              <div class="mt-3" v-if="paiement.statut==='en_attente'">
                <v-btn
                  color="primary"
                  :loading="loadingStripe"
                  @click="payerMaintenant"
                  prepend-icon="mdi-credit-card-outline"
                >
                  Payer maintenant (test)
                </v-btn>
                <div class="text-caption mt-2">
                  Carte test : <b>4242 4242 4242 4242</b> — Date future, CVC 123, ZIP 75001.
                </div>
              </div>
            </div>

            <v-btn
              v-else
              color="primary"
              :loading="loadingPay"
              @click="creerPaiement"
              prepend-icon="mdi-credit-card-outline"
            >
              Créer le paiement
            </v-btn>
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
const loadingCreate = ref(false)
const loadingSign = ref(false)
const loadingPay = ref(false)
const loadingStripe = ref(false)

const contrat = ref<any | null>(null)
const paiement = ref<any | null>(null)

const snackbar = ref({ show: false, text: '', color: 'success' })

function fileHref(u: string) {
  if (/^https?:\/\//i.test(u)) return u
  return `${API}/${String(u).replace(/^\/+/, '')}`
}

async function loadContrat() {
  if (!cid.value) return
  const { data } = await axios.get(`${API}/contrats/get_contrat.php`, {
    params: { candidature_id: cid.value },
    headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` },
  })
  if (!data?.success) throw new Error(data?.message || 'Erreur chargement contrat')
  contrat.value = data.data || null
  if (contrat.value?.id) await loadPaiement()
}

async function loadPaiement() {
  const { data } = await axios.get(`${API}/paiements/get_paiement.php`, {
    params: { contrat_id: contrat.value.id },
    headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` },
  })
  if (data?.success) paiement.value = data.data
}

async function creerContrat() {
  if (!cid.value) return
  try {
    loadingCreate.value = true
    const { data } = await axios.post(
      `${API}/contrats/creer_contrat.php`,
      { candidature_id: Number(cid.value) },
      { headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` } }
    )
    if (!data?.success) throw new Error(data?.message || 'Erreur création contrat')
    snackbar.value = { show: true, text: 'Contrat généré', color: 'success' }
    await loadContrat()
  } catch (e: any) {
    snackbar.value = { show: true, text: e?.message || 'Erreur', color: 'error' }
  } finally {
    loadingCreate.value = false
  }
}

async function signer() {
  if (!cid.value) return
  try {
    loadingSign.value = true
    const { data } = await axios.post(
      `${API}/contrats/signer_contrat.php`,
      { candidature_id: Number(cid.value) },
      { headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` } }
    )
    if (!data?.success) throw new Error(data?.message || 'Erreur signature')
    snackbar.value = { show: true, text: 'Contrat signé (annonceur)', color: 'success' }
    await loadContrat()
  } catch (e: any) {
    snackbar.value = { show: true, text: e?.message || 'Erreur', color: 'error' }
  } finally {
    loadingSign.value = false
  }
}

async function creerPaiement() {
  if (!contrat.value?.id) return
  try {
    loadingPay.value = true
    const { data } = await axios.post(
      `${API}/paiements/creer_paiement.php`,
      { contrat_id: contrat.value.id, montant_total: 500 }, // TODO: calcule réel
      { headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` } }
    )
    if (!data?.success) throw new Error(data?.message || 'Erreur création paiement')
    paiement.value = data.data
    snackbar.value = { show: true, text: 'Paiement créé', color: 'success' }
  } catch (e: any) {
    snackbar.value = { show: true, text: e?.message || 'Erreur', color: 'error' }
  } finally {
    loadingPay.value = false
  }
}

async function payerMaintenant() {
  if (!contrat.value?.id) return
  try {
    loadingStripe.value = true
    const q = encodeURIComponent(String(cid.value || ''))
    const { data } = await axios.post(
      `${API}/paiements/stripe_create_checkout.php?candidature_id=${q}`,
      { contrat_id: contrat.value.id },
      { headers: { Authorization: `Bearer ${localStorage.getItem('token') || ''}` } }
    )
    if (!data?.success) throw new Error(data?.message || 'Erreur Stripe')
    window.location.href = data.data.checkout_url
  } catch (e: any) {
    snackbar.value = { show: true, text: e?.message || 'Erreur Stripe', color: 'error' }
  } finally {
    loadingStripe.value = false
  }
}

async function init() {
  try {
    loading.value = true
    await loadContrat()
  } catch (e: any) {
    snackbar.value = { show: true, text: e?.message || 'Erreur', color: 'error' }
  } finally {
    loading.value = false
  }
}

const verifyLoading = ref(false)
async function verifyStripeStatus(){
  if(!contrat.value?.id) return
  try{
    verifyLoading.value = true
    const { data } = await axios.post(
      `${API}/paiements/stripe_verify_session.php`,
      { contrat_id: contrat.value.id },
      { headers:{ Authorization:`Bearer ${localStorage.getItem('token')||''}` } }
    )
    if(data?.success){
      await loadPaiement()
      if(data.data?.statut==='paye'){
        snackbar.value = { show:true, text:'Paiement confirmé 🎉', color:'success' }
      }
    }
  } finally { verifyLoading.value = false }
}

onMounted(async () => {
  const u = new URL(window.location.href)
  const q = u.searchParams.get('candidatureId')
  const paid = u.searchParams.get('paid')
  if (q) { cid.value = q; await init() }
  if (paid === '1') await verifyStripeStatus()
})

</script>
