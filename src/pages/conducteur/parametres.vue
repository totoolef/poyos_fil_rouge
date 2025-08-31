<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <h2 class="text-h6 mb-4">Paramètres de votre compte</h2>

      <v-progress-linear v-if="enChargement" indeterminate color="primary" class="mb-4" />

      <v-alert v-if="messageErreur" type="error" class="mb-4">{{ messageErreur }}</v-alert>

      <v-form v-else @submit.prevent="soumettreModifications">
        <!-- Infos de compte -->
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.email"
              label="Email"
              type="email"
              variant="outlined"
              :rules="[regles.obligatoire, regles.emailValide]"
              required
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.motDePasse"
              label="Nouveau mot de passe"
              type="password"
              variant="outlined"
              :rules="[regles.motDePasseValide]"
              hint="Laissez vide pour ne pas changer"
              persistent-hint
            />
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.nom"
              label="Nom"
              variant="outlined"
              :rules="[regles.obligatoire]"
              required
            />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.prenom"
              label="Prénom"
              variant="outlined"
              :rules="[regles.obligatoire]"
              required
            />
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12">
            <v-text-field
              v-model="formulaire.adresse"
              label="Adresse"
              variant="outlined"
              :rules="[regles.obligatoire]"
              required
            />
          </v-col>
        </v-row>

        <v-btn color="primary" type="submit" :loading="enCoursDeSoumission" class="mt-2">
          Enregistrer les modifications
        </v-btn>

        <v-alert v-if="messageSucces" type="success" class="mt-4">{{ messageSucces }}</v-alert>
        <v-alert v-if="messageErreur" type="error" class="mt-4">{{ messageErreur }}</v-alert>
      </v-form>
    </v-card>

    <!-- ----------------------------------- -->
    <!-- Documents requis du conducteur -->
    <!-- ----------------------------------- -->
    <v-card class="elevation-3 rounded-lg pa-4 mt-6">
      <div class="d-flex align-center justify-space-between mb-2">
        <h2 class="text-h6 mb-0">Mes documents (conducteur)</h2>
        <v-chip :color="resume.ok ? 'green' : 'orange'" variant="flat" size="small">
          {{ resume.ok ? 'Complet' : 'Incomplet' }}
        </v-chip>
      </div>
      <p class="text-body-2 mb-6">
        Merci de fournir ces documents pour pouvoir être sélectionné sur une campagne.
      </p>

      <v-row>
        <!-- Permis -->
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="pa-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <v-icon class="mr-2">mdi-card-account-details</v-icon>
                <div>
                  <div class="font-weight-medium">Permis de conduire</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ statutTexte(documents.permis) }}
                  </div>
                </div>
              </div>
              <v-chip :color="couleurStatut(documents.permis)" size="small" variant="flat">
                {{ libelleStatut(documents.permis) }}
              </v-chip>
            </div>

            <div class="mt-3">
              <v-file-input
                label="Téléverser ou remplacer"
                prepend-icon="mdi-upload"
                accept="image/*,application/pdf"
                :disabled="uploading.permis"
                @update:modelValue="files => onFileSelected('permis', files)"
                show-size
                variant="outlined"
                density="comfortable"
              />
              <div class="d-flex gap-2 mt-2">
                <v-btn
                  v-if="documents.permis?.url"
                  size="small"
                  variant="text"
                  @click="ouvrir(documents.permis.url)"
                >
                  Voir le document
                </v-btn>
                <v-btn
                  v-if="documents.permis?.id"
                  size="small"
                  variant="text"
                  color="error"
                  @click="supprimer('permis')"
                >
                  Supprimer
                </v-btn>
              </div>
            </div>
          </v-card>
        </v-col>

        <!-- Carte grise -->
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="pa-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <v-icon class="mr-2">mdi-car-info</v-icon>
                <div>
                  <div class="font-weight-medium">Carte grise</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ statutTexte(documents.carte_grise) }}
                  </div>
                </div>
              </div>
              <v-chip :color="couleurStatut(documents.carte_grise)" size="small" variant="flat">
                {{ libelleStatut(documents.carte_grise) }}
              </v-chip>
            </div>

            <div class="mt-3">
              <v-file-input
                label="Téléverser ou remplacer"
                prepend-icon="mdi-upload"
                accept="image/*,application/pdf"
                :disabled="uploading.carte_grise"
                @update:modelValue="files => onFileSelected('carte_grise', files)"
                show-size
                variant="outlined"
                density="comfortable"
              />
              <div class="d-flex gap-2 mt-2">
                <v-btn v-if="documents.carte_grise?.url" size="small" variant="text" @click="ouvrir(documents.carte_grise.url)">
                  Voir le document
                </v-btn>
                <v-btn v-if="documents.carte_grise?.id" size="small" variant="text" color="error" @click="supprimer('carte_grise')">
                  Supprimer
                </v-btn>
              </div>
            </div>
          </v-card>
        </v-col>

        <!-- Assurance -->
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="pa-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <v-icon class="mr-2">mdi-shield-check</v-icon>
                <div>
                  <div class="font-weight-medium">Attestation d’assurance</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ statutTexte(documents.assurance) }}
                  </div>
                </div>
              </div>
              <v-chip :color="couleurStatut(documents.assurance)" size="small" variant="flat">
                {{ libelleStatut(documents.assurance) }}
              </v-chip>
            </div>

            <div class="mt-3">
              <v-file-input
                label="Téléverser ou remplacer"
                prepend-icon="mdi-upload"
                accept="image/*,application/pdf"
                :disabled="uploading.assurance"
                @update:modelValue="files => onFileSelected('assurance', files)"
                show-size
                variant="outlined"
                density="comfortable"
              />
              <div class="d-flex gap-2 mt-2">
                <v-btn v-if="documents.assurance?.url" size="small" variant="text" @click="ouvrir(documents.assurance.url)">
                  Voir le document
                </v-btn>
                <v-btn v-if="documents.assurance?.id" size="small" variant="text" color="error" @click="supprimer('assurance')">
                  Supprimer
                </v-btn>
              </div>
            </div>
          </v-card>
        </v-col>

        <!-- Contrôle technique -->
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="pa-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <v-icon class="mr-2">mdi-wrench-clock</v-icon>
                <div>
                  <div class="font-weight-medium">Contrôle technique</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ statutTexte(documents.controle_technique) }}
                  </div>
                </div>
              </div>
              <v-chip :color="couleurStatut(documents.controle_technique)" size="small" variant="flat">
                {{ libelleStatut(documents.controle_technique) }}
              </v-chip>
            </div>

            <div class="mt-3">
              <v-file-input
                label="Téléverser ou remplacer"
                prepend-icon="mdi-upload"
                accept="image/*,application/pdf"
                :disabled="uploading.controle_technique"
                @update:modelValue="files => onFileSelected('controle_technique', files)"
                show-size
                variant="outlined"
                density="comfortable"
              />
              <div class="d-flex gap-2 mt-2">
                <v-btn v-if="documents.controle_technique?.url" size="small" variant="text" @click="ouvrir(documents.controle_technique.url)">
                  Voir le document
                </v-btn>
                <v-btn v-if="documents.controle_technique?.id" size="small" variant="text" color="error" @click="supprimer('controle_technique')">
                  Supprimer
                </v-btn>
              </div>
            </div>
          </v-card>
        </v-col>

        <!-- Photos récentes véhicule (multiple) -->
        <v-col cols="12">
          <v-card variant="outlined" class="pa-3">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center">
                <v-icon class="mr-2">mdi-camera</v-icon>
                <div>
                  <div class="font-weight-medium">Photos récentes du véhicule</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ photoItems.length ? photoItems.length + ' photo(s) envoyée(s)' : 'Aucune photo' }}
                    </div>

                </div>
              </div>
              <v-chip :color="photoItems.length ? 'green' : 'orange'" size="small" variant="flat">
                {{ photoItems.length ? 'OK' : 'Manquant' }}
              </v-chip>

            </div>

            <div class="mt-3">
              <v-file-input
                label="Ajouter des photos (jusqu’à 6)"
                prepend-icon="mdi-upload"
                accept="image/*"
                multiple
                :counter="true"
                :counter-size-string="'/ 6'"
                :disabled="uploading.photos_vehicule"
                @update:modelValue="onMultiplePhotos"
                variant="outlined"
                density="comfortable"
              />

              <div v-if="photoItems.length" class="d-flex flex-wrap gap-2 mt-2">
                <div v-for="ph in photoItems" :key="ph.id" class="position-relative">
                  <v-img :src="ph.url" aspect-ratio="16/9" width="160" class="rounded" cover />
                  <v-btn
                    icon="mdi-delete"
                    size="x-small"
                    variant="elevated"
                    color="error"
                    class="position-absolute"
                    style="top:6px; right:6px"
                    @click="ph.id > 0 ? supprimerPhoto(ph.id) : null"
                  />
                </div>
              </div>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <v-alert v-if="messageDoc" :type="messageDoc.type" class="mt-4">
        {{ messageDoc.text }}
      </v-alert>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

/** -------------------------
 *  Types & état
 *  ------------------------*/
type DocKey = 'permis'|'carte_grise'|'assurance'|'controle_technique'|'photos_vehicule'
type PhotoItem = { id: number; url: string } // si pas déjà défini

const photoItems = computed<PhotoItem[]>(() => {
  const pv = documents.value.photos_vehicule
  // Compat si tu reçois encore `urls` depuis un cache
  if (pv?.items && pv.items.length) return pv.items
  if (pv?.urls && pv.urls.length) return pv.urls.map((u, i) => ({ id: -(i+1), url: u }))
  return []
})

interface DocItem {
  id?: number
  url?: string
  urls?: string[] // pour photos_vehicule
  items?: PhotoItem[]
  status?: 'ok'|'manquant'|'expire'
  expires_at?: string | null
}

interface DocumentsState {
  permis: DocItem
  carte_grise: DocItem
  assurance: DocItem
  controle_technique: DocItem
  photos_vehicule: DocItem
}

interface Formulaire {
  email: string
  motDePasse: string
  nom: string
  prenom: string
  adresse: string
}

const formulaire = ref<Formulaire>({
  email: '',
  motDePasse: '',
  nom: '',
  prenom: '',
  adresse: '',
})

const documents = ref<DocumentsState>({
  permis: {},
  carte_grise: {},
  assurance: {},
  controle_technique: {},
  photos_vehicule: { items: []},
})

const uploading = ref<Record<DocKey, boolean>>({
  permis: false,
  carte_grise: false,
  assurance: false,
  controle_technique: false,
  photos_vehicule: false,
})

const enCoursDeSoumission = ref(false)
const enChargement = ref(true)
const messageSucces = ref<string | null>(null)
const messageErreur = ref<string | null>(null)
const messageDoc = ref<{type:'success'|'error'|'info', text:string} | null>(null)
const token = localStorage.getItem('token')

const regles = {
  obligatoire: (v: string) => !!v || 'Ce champ est requis',
  emailValide: (v: string) => /.+@.+\..+/.test(v) || 'Email invalide',
  motDePasseValide: (v: string) => !v || v.length >= 6 || 'Minimum 6 caractères',
}

const resume = computed(() => {
  const allOk =
    documents.value.permis?.status === 'ok' &&
    documents.value.carte_grise?.status === 'ok' &&
    documents.value.assurance?.status === 'ok' &&
    documents.value.controle_technique?.status === 'ok' &&
    photoItems.value.length > 0
  return { ok: allOk }
})

/** -------------------------
 *  Helpers affichage
 *  ------------------------*/
function libelleStatut(d?: DocItem) {
  if (!d?.status) return 'Manquant'
  return d.status === 'ok' ? 'OK' : d.status === 'expire' ? 'Expiré' : 'Manquant'
}
function couleurStatut(d?: DocItem) {
  if (!d?.status) return 'orange'
  return d.status === 'ok' ? 'green' : d.status === 'expire' ? 'red' : 'orange'
}
function statutTexte(d?: DocItem) {
  if (!d?.status) return 'Aucun fichier envoyé'
  if (d.status === 'ok' && d.expires_at) return `Valide jusqu’au ${new Date(d.expires_at).toLocaleDateString()}`
  if (d.status === 'expire') return 'Document expiré — merci de remplacer'
  return 'Document manquant'
}

function ouvrir(url?: string) {
  if (url) window.open(url, '_blank')
}

/** -------------------------
 *  Chargement initial
 *  ------------------------*/
onMounted(async () => {
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter'
    enChargement.value = false
    return
  }

  try {
    // Infos utilisateur
    const u = await axios.get('http://localhost:8080/utilisateurs/get_utilisateur_infos_conducteur.php', {
      headers: { Authorization: `Bearer ${token}` },
    })
    if (u.data?.success) {
      const user = u.data.user
      formulaire.value.email = user.email
      formulaire.value.nom = user.nom
      formulaire.value.prenom = user.prenom
      formulaire.value.adresse = user.adresse || ''
    }

    // Statut documents
    await rafraichirDocuments()
  } catch (e) {
    console.error(e)
    messageErreur.value = 'Erreur de chargement'
  } finally {
    enChargement.value = false
  }
})

async function rafraichirDocuments() {
  try {
    const r = await axios.get('http://localhost:8080/documents/get_documents_conducteur.php', {
      headers: { Authorization: `Bearer ${token}` },
    })
    // On attend un objet { success, documents: {permis:{...}, ...} }
    if (r.data?.success) {
      documents.value = r.data.documents as DocumentsState
    }
    // Compat: si le back renvoie encore `urls`, on fabrique des `items`
    const pv = documents.value.photos_vehicule
    if (pv && !pv.items && pv.urls && pv.urls.length) {
      pv.items = pv.urls.map((u, i) => ({ id: -(i+1), url: u })) // ids négatifs factices (pas supprimables)
    }

  } catch (e) {
    console.error(e)
  }
}

/** -------------------------
 *  Uploads
 *  ------------------------*/
async function onFileSelected(type: DocKey, files: File[] | File | null) {
  const file = Array.isArray(files) ? files[0] : files
  if (!file) return
  try {
    uploading.value[type] = true
    const fd = new FormData()
    fd.append('type', type)
    fd.append('file', file)
    // Optionnel : date d’expiration (ex. assurance), à saisir via un petit datepicker si tu veux
    // fd.append('expires_at', '2025-12-31')

    const resp = await axios.post('http://localhost:8080/documents/upload_document_conducteur.php', fd, {
      headers: { Authorization: `Bearer ${token}` },
    })
    if (resp.data?.success) {
      messageDoc.value = { type: 'success', text: 'Document envoyé 👍' }
      await rafraichirDocuments()
    } else {
      messageDoc.value = { type: 'error', text: resp.data?.message || 'Échec de l’envoi' }
    }
  } catch (e) {
    console.error(e)
    messageDoc.value = { type: 'error', text: 'Erreur réseau pendant l’envoi' }
  } finally {
    uploading.value[type] = false
  }
}

async function onMultiplePhotos(files: File[] | File | null) {
  const list = Array.isArray(files) ? files : (files ? [files] : [])
  if (!list.length) return
  try {
    uploading.value.photos_vehicule = true
    const fd = new FormData()
    fd.append('type', 'photos_vehicule')
    list.slice(0, 6).forEach((f) => fd.append('files[]', f))
    const resp = await axios.post('http://localhost:8080/documents/upload_document_conducteur.php', fd, {
      headers: { Authorization: `Bearer ${token}` },
    })
    if (resp.data?.success) {
      messageDoc.value = { type: 'success', text: 'Photos envoyées 👍' }
      await rafraichirDocuments()
    } else {
      messageDoc.value = { type: 'error', text: resp.data?.message || 'Échec de l’envoi' }
    }
  } catch (e) {
    console.error(e)
    messageDoc.value = { type: 'error', text: 'Erreur réseau pendant l’envoi' }
  } finally {
    uploading.value.photos_vehicule = false
  }
}

async function supprimer(type: DocKey) {
  try {
    const id = (documents.value as any)[type]?.id
    if (!id) return
    const resp = await axios.delete(`http://localhost:8080/documents/supprimer_document_conducteur.php?id=${id}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    if (resp.data?.success) {
      messageDoc.value = { type: 'success', text: 'Document supprimé' }
      await rafraichirDocuments()
    } else {
      messageDoc.value = { type: 'error', text: resp.data?.message || 'Échec de la suppression' }
    }
  } catch (e) {
    console.error(e)
    messageDoc.value = { type: 'error', text: 'Erreur réseau pendant la suppression' }
  }
}

async function supprimerPhoto(photoId: number) {
  try {
    const resp = await axios.delete(`http://localhost:8080/documents/supprimer_document_conducteur.php?id=${photoId}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
    if (resp.data?.success) {
      messageDoc.value = { type: 'success', text: 'Photo supprimée' }
      await rafraichirDocuments()
    } else {
      messageDoc.value = { type: 'error', text: resp.data?.message || 'Échec de la suppression' }
    }
  } catch (e) {
    console.error(e)
    messageDoc.value = { type: 'error', text: 'Erreur réseau pendant la suppression' }
  }
}

/** -------------------------
 *  Soumission infos de compte
 *  ------------------------*/
const soumettreModifications = async () => {
  enCoursDeSoumission.value = true
  messageSucces.value = null
  messageErreur.value = null
  try {
    const reponse = await axios.post(
      'http://localhost:8080/utilisateurs/modifier_parametres_conducteur.php',
      {
        id: localStorage.getItem('userId'),
        email: formulaire.value.email,
        mot_de_passe: formulaire.value.motDePasse, // vide = pas de changement côté back
        nom: formulaire.value.nom,
        prenom: formulaire.value.prenom,
        adresse: formulaire.value.adresse,
      },
      {
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
      }
    )
    if (reponse.data.success) {
      messageSucces.value = 'Paramètres mis à jour avec succès !'
      formulaire.value.motDePasse = '' // reset champ
    } else {
      messageErreur.value = reponse.data.message || 'Échec de la mise à jour'
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur)
    messageErreur.value = 'Erreur de connexion au serveur'
  } finally {
    enCoursDeSoumission.value = false
  }
}
</script>