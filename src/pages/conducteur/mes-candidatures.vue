<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <h2 class="text-h6 mb-4">Mes candidatures envoyées</h2>
      <v-select
        v-model="filtreStatut"
        :items="statutsFiltres"
        label="Filtrer par statut"
        variant="outlined"
        prepend-icon="mdi-filter"
        class="mb-4"
        clearable
      ></v-select>
      <v-progress-linear v-if="enChargement" indeterminate color="primary" class="mb-4"></v-progress-linear>
      <v-data-table
        :headers="entetes"
        :items="candidaturesFiltrees"
        :items-per-page="5"
        class="elevation-1 d-none d-md-table"
      >
        <template v-slot:item.statut="{ item }">
          <v-chip :color="getStatutColor(item.statut)" size="small">
            {{ item.statut }}
          </v-chip>
        </template>
        <template v-slot:item.actions="{ item }">
          <v-btn color="red" variant="outlined" size="small" @click="annulerCandidature(item)">
            Annuler
          </v-btn>
        </template>
      </v-data-table>
      <v-row class="d-md-none">
        <v-col v-for="(candidature, index) in candidaturesFiltrees" :key="index" cols="12">
          <v-card class="elevation-2 pa-4">
            <v-card-title class="text-subtitle-1">{{ candidature.titre_annonce }}</v-card-title>
            <v-card-subtitle>{{ candidature.localisation }}</v-card-subtitle>
            <v-card-text>
              <p>Message : {{ candidature.message.substring(0, 100) }}...</p>
              <p>Date : {{ candidature.created_at }}</p>
            </v-card-text>
            <v-card-actions>
              <v-chip :color="getStatutColor(candidature.statut)" size="small">
                {{ candidature.statut }}
              </v-chip>
              <v-spacer></v-spacer>
              <v-btn color="red" variant="outlined" size="small" @click="annulerCandidature(candidature)">
                Annuler
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
      <v-alert v-if="!candidaturesFiltrees.length && !enChargement" type="info" class="mt-4">Aucune candidature correspondant à vos critères.</v-alert>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

// Types pour les données
interface Candidature {
  id: number;
  annonce_id: number;
  titre_annonce: string; // À joindre via SQL
  localisation: string; // À joindre via SQL
  message: string;
  statut: string;
  created_at: string;
  updated_at: string;
}

const candidatures = ref<Candidature[]>([]);
const filtreStatut = ref<string | null>(null);
const enChargement = ref<boolean>(true);
const messageErreur = ref<string | null>(null);

const entetes = [
  { title: 'Titre annonce', key: 'titre_annonce' },
  { title: 'Localisation', key: 'localisation' },
  { title: 'Message', key: 'message' },
  { title: 'Date', key: 'created_at' },
  { title: 'Statut', key: 'statut' },
  { title: 'Actions', key: 'actions', sortable: false },
] as const;

const statutsFiltres = ['en_attente', 'refusee', 'annulee', 'acceptee']; // Basé sur la BDD

const candidaturesFiltrees = computed(() => {
  if (filtreStatut.value) {
    return candidatures.value.filter(candidature => candidature.statut === filtreStatut.value);
  }
  return candidatures.value;
});

const getStatutColor = (statut: string) => {
  switch (statut) {
    case 'en_attente': return 'warning';
    case 'acceptee': return 'success';
    case 'refusee': return 'error';
    case 'annulee': return 'grey';
    default: return 'grey';
  }
};

// Fonction pour charger les candidatures
const chargerCandidatures = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter';
    enChargement.value = false;
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8000/get_candidatures_conducteur.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      candidatures.value = reponse.data.candidatures as Candidature[];
    } else {
      messageErreur.value = reponse.data.message || 'Échec du chargement des candidatures';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  } finally {
    enChargement.value = false;
  }
};

onMounted(chargerCandidatures);

const annulerCandidature = async (candidature: Candidature) => {
  if (confirm('Voulez-vous vraiment annuler cette candidature ?')) {
    try {
      const token = localStorage.getItem('token');
      const reponse = await axios.post('http://localhost:8000/annuler_candidature.php', {
        candidature_id: candidature.id,
      }, {
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      });
      if (reponse.data.success) {
        alert('Candidature annulée avec succès');
        chargerCandidatures(); // Rafraîchir la liste
      } else {
        alert(reponse.data.message || 'Échec de l\'annulation');
      }
    } catch (erreur) {
      console.error('Erreur Axios:', erreur);
      alert('Erreur de connexion au serveur');
    }
  }
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>