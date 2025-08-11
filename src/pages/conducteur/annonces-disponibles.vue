<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-4 rounded-xl pa-6">
      <div class="d-flex justify-space-between align-center mb-6">
        <h2 class="text-h5 font-weight-bold">📢 Annonces disponibles</h2>
      </div>

    <v-radio-group v-model="typeRecherche" row>
        <v-radio value="non-local" density="compact">
            <template #label>
            <v-icon start>mdi-web</v-icon>
                Non local
            </template>
        </v-radio>
        <v-radio value="local" density="compact">
            <template #label>
            <v-icon start>mdi-map-marker</v-icon>
                Local
            </template>
        </v-radio>
    </v-radio-group>


      <v-text-field
        v-if="typeRecherche === 'local'"
        v-model="codePostalRecherche"
        label="Code postal (ex. 57)"
        variant="outlined"
        prepend-icon="mdi-map-marker"
        class="mb-4"
        @update:model-value="chargerAnnonces"
      ></v-text-field>

      <v-progress-linear v-if="enChargement" indeterminate color="primary" class="mb-4"></v-progress-linear>

      <v-row>
        <v-col
          v-for="(annonce, index) in annoncesFiltrees"
          :key="index"
          cols="12"
          md="4"
        >
          <v-hover v-slot:default="{ isHovering, props }">
            <v-card
              v-bind="props"
              class="elevation-2 pa-4 rounded-lg transition-swing"
              :class="{ 'elevation-6': isHovering }"
              @click="voirDetails(annonce)"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <v-card-title class="text-subtitle-1 font-weight-bold">{{ annonce.titre }}</v-card-title>
                <v-chip size="small" :color="getStatutColor(annonce.statut)" text-color="white">
                  {{ annonce.statut }}
                </v-chip>
              </div>
              <v-card-subtitle class="text-caption mb-2">
                <v-icon icon="mdi-map-marker" size="16" class="mr-1" /> {{ annonce.localisation }}
              </v-card-subtitle>
              <v-card-text>
                <p class="text-body-2 mb-2">{{ annonce.description.substring(0, 120) }}...</p>
                <div class="mb-1">
                  <v-icon icon="mdi-tag" size="16" class="mr-1" />
                  Type : <strong>{{ annonce.type_pub }}</strong>
                </div>
                <div class="mb-1">
                  <v-icon icon="mdi-cash" size="16" class="mr-1" />
                  Paiement : <strong>{{ annonce.paiement_mensuel }} €</strong>/mois
                </div>
                <div>
                  <v-icon icon="mdi-calendar-clock" size="16" class="mr-1" />
                  Durée : <strong>{{ annonce.duree_mois }} mois</strong>
                </div>
              </v-card-text>
            </v-card>
          </v-hover>
        </v-col>
      </v-row>

      <v-alert v-if="!annoncesFiltrees.length && !enChargement" type="info" class="mt-6">
        Aucune annonce disponible pour votre recherche.
      </v-alert>
    </v-card>
</v-container>

<v-dialog v-model="voirAnnonce" max-width="700px">
  <VoirAnnonce
    v-if="annonceSelectionnee"
    :annonce="annonceSelectionnee"
    @close="fermerVoirAnnonce"
    @candidater="candidater(annonceSelectionnee)"
  />
</v-dialog>

<v-dialog v-model="dialogCandidature" max-width="600px">
  <CandidaterAnnonce
    v-if="annonceSelectionnee"
    :annonce-id="annonceSelectionnee.id"
    @close="fermerDialogCandidature"
    @actualiser="chargerAnnonces"
  />
</v-dialog>

</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import VoirAnnonce from '../../dialog/VoirAnnonce.vue';
import CandidaterAnnonce from '../../dialog/CandidaterAnnonce.vue';

interface Annonce {
  id: number;
  annonceur_id: number;
  type_pub: string;
  titre: string;
  description: string;
  localisation_type: string;
  localisation_personnalisee: string;
  code_postal: string;
  ville: string;
  localisation: string;
  nombre_vehicules: number;
  duree_mois: number;
  paiement_mensuel: number;
  statut: string;
  created_at: string;
  updated_at: string;
}

const annonces = ref<Annonce[]>([]);
const typeRecherche = ref('non-local');
const codePostalRecherche = ref('');
const enChargement = ref(true);
const messageErreur = ref<string | null>(null);
const voirAnnonce = ref<boolean>(false);
const annonceSelectionnee = ref<Annonce | null>(null);
const dialogCandidature = ref(false);

const annoncesFiltrees = computed(() => {
  if (typeRecherche.value === 'non-local') {
    return annonces.value.filter(a => a.localisation_type === 'personnalise' && a.statut === 'ouverte');
  } else if (typeRecherche.value === 'local' && codePostalRecherche.value) {
    return annonces.value.filter(
      a => a.localisation_type === 'precis' &&
      a.code_postal.startsWith(codePostalRecherche.value) &&
      a.statut === 'ouverte'
    );
  }
  return [];
});

const getStatutColor = (statut: string) => {
  switch (statut) {
    case 'ouverte': return 'success';
    case 'en_cours': return 'warning';
    case 'fermee': return 'error';
    default: return 'grey';
  }
};

const chargerAnnonces = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter';
    enChargement.value = false;
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8000/get_annonces_disponibles.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      annonces.value = reponse.data.annonces;
    } else {
      messageErreur.value = reponse.data.message || 'Échec du chargement des annonces';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  } finally {
    enChargement.value = false;
  }
};

const voirDetails = (annonce: Annonce) => {
  annonceSelectionnee.value = annonce;
  voirAnnonce.value = true;
};

const fermerVoirAnnonce = () => {
  voirAnnonce.value = false;
};

// Quand on veut candidater à une annonce
const candidater = (annonce: any) => {
  annonceSelectionnee.value = annonce;
  dialogCandidature.value = true;
};

// Fermer le dialog
const fermerDialogCandidature = () => {
  dialogCandidature.value = false;
};

onMounted(chargerAnnonces);
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
.transition-swing {
  transition: all 0.25s ease-in-out;
}
</style>
