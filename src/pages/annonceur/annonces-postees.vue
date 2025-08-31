<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <h2 class="text-h6 mb-4">Vos annonces postées</h2>
      <v-text-field
        v-model="recherche"
        label="Rechercher par titre ou localisation"
        variant="outlined"
        prepend-icon="mdi-magnify"
        class="mb-4"
      ></v-text-field>
      <v-data-table
        :headers="entetes"
        :items="annoncesFiltrees"
        :items-per-page="5"
        class="elevation-1 d-none d-md-table"
      >
        <template v-slot:item.titre="{ item }">
          <span @click="ouvrirModifierAnnonce(item)" class="cursor-pointer text-primary">{{ item.titre }}</span>
        </template>
        <template v-slot:item.statut="{ item }">
          <v-chip :color="getStatutColor(item.statut)" size="small">
            {{ item.statut }}
          </v-chip>
        </template>
      </v-data-table>
      <v-row class="d-md-none">  <!-- Cartes sur mobile -->
        <v-col v-for="(annonce, index) in annoncesFiltrees" :key="index" cols="12">
          <v-card class="elevation-2 pa-4">
            <v-card-title class="text-subtitle-1"><span @click="ouvrirModifierAnnonce(annonce)" class="cursor-pointer text-primary">{{ annonce.titre }}</span></v-card-title>
            <v-card-subtitle>{{ annonce.localisation }}</v-card-subtitle>
            <v-card-text>
              <p>{{ annonce.description }}</p>
              <p>Type : {{ annonce.type_pub }}</p>
              <p>Paiement/mois : {{ annonce.paiement_mensuel }} €</p>
              <p>Durée : {{ annonce.duree_mois }} mois</p>
            </v-card-text>
            <v-card-actions>
              <v-chip :color="getStatutColor(annonce.statut)" size="small">
                {{ annonce.statut }}
              </v-chip>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
      <v-alert v-if="!annonces.length" type="info" class="mt-4">Aucune annonce postée pour le moment.</v-alert>
    </v-card>
  </v-container>
  <v-dialog v-model="modifierAnnonce" max-width="700">
    <ModifierAnnonce :annonce="annonceSelectionnee" @fermer="fermerModifierAnnonce" @actualiser="chargerAnnonces" />
  </v-dialog>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import ModifierAnnonce from '../../dialog/ModifierAnnonce.vue';

// Types pour les données
interface Annonce {
  id: number;
  annonceur_id: number;
  type_pub: string;
  titre: string;
  description: string;
  localisation: string;
  nombre_vehicules: number;
  duree_mois: number;
  paiement_mensuel: number;
  statut: string;
  created_at: string;
  updated_at: string;
}

const annonces = ref<Annonce[]>([]);
const recherche = ref<string>('');
const enChargement = ref<boolean>(true);
const messageErreur = ref<string | null>(null);
const modifierAnnonce = ref<boolean>(false);
const annonceSelectionnee = ref<Annonce | null>(null);

const entetes = [
  { title: 'Titre', key: 'titre' },
  { title: 'Type de pub', key: 'type_pub' },
  { title: 'Description', key: 'description' },
  { title: 'Localisation', key: 'localisation' },
  { title: 'Paiement/mois (€)', key: 'paiement_mensuel' },
  { title: 'Durée (mois)', key: 'duree_mois' },
  { title: 'Statut', key: 'statut' },
] as const;

const annoncesFiltrees = computed(() => {
  return annonces.value.filter(annonce =>
    annonce.titre.toLowerCase().includes(recherche.value.toLowerCase()) ||
    annonce.localisation.toLowerCase().includes(recherche.value.toLowerCase())
  );
});

const getStatutColor = (statut: string) => {
  switch (statut) {
    case 'ouverte': return 'success';
    case 'en_cours': return 'warning';
    case 'fermee': return 'error';
    default: return 'grey';
  }
};

const ouvrirModifierAnnonce = (annonce: Annonce) => {
  annonceSelectionnee.value = annonce;
  modifierAnnonce.value = true;
};

const fermerModifierAnnonce = () => {
  modifierAnnonce.value = false;
  annonceSelectionnee.value = null;
};

const chargerAnnonces = async () => {
  enChargement.value = true;
  const token = localStorage.getItem('token');
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter';
    enChargement.value = false;
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8080/annonces/liste_annonces.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      annonces.value = reponse.data.annonces as Annonce[];
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

onMounted(chargerAnnonces);
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>