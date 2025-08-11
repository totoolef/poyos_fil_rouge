<template>
  <v-app id="dashboard-conducteur-layout">
    <v-app-bar app color="teal-lighten-2" flat>
      <!-- Titre et icône -->
      <v-toolbar-title class="text-white">Dashboard Conducteur - {{ nom }}</v-toolbar-title>
      <v-spacer></v-spacer>
      <!-- Icône pour ouvrir le drawer sur mobile -->
      <v-app-bar-nav-icon class="d-flex d-md-none" @click="drawer = !drawer"></v-app-bar-nav-icon>
      <!-- Bouton rapide pour nouvelles annonces -->
      <v-btn icon color="white" @click="routeur.push('/dashboard/annonces-disponibles')" class="mr-2">
        <v-icon>mdi-bell-plus</v-icon>
      </v-btn>
    </v-app-bar>

    <!-- Navigation Drawer latéral avec statistiques -->
    <v-navigation-drawer
      v-model="drawer"
      :permanent="permanent"
      app
      color="teal-lighten-4"
      width="260"
      class="elevation-2"
      :rail="rail"
    >
      <v-list nav density="compact">
        <!-- Section statistiques rapides -->
        <v-list-subheader class="text-subtitle-1 font-weight-bold">Statistiques</v-list-subheader>
        <v-list-item
          prepend-icon="mdi-cash"
          title="Gains totaux"
          :subtitle="`≈ ${gainTotal} €`"
          class="animated-item"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-calendar-check"
          title="Contrats actifs"
          :subtitle="contratsActifs.length"
          class="animated-item"
        ></v-list-item>
        <v-divider class="my-2"></v-divider>

        <!-- Menu principal -->
        <v-list-subheader class="text-subtitle-1 font-weight-bold">Navigation</v-list-subheader>
        <v-list-item
          prepend-icon="mdi-magnify"
          title="Annonces disponibles"
          to="/dashboard-conducteur/annonces-disponibles"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-account-check"
          title="Mes candidatures"
          to="/dashboard-conducteur/mes-candidatures"
          :badge-props="{ content: candidaturesEnAttente.length, color: 'warning' }"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-file-document"
          title="Mes contrats"
          to="/dashboard/mes-contrats"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-currency-usd"
          title="Mes paiements"
          to="/dashboard/mes-paiements"
        ></v-list-item>
        <v-divider class="my-2"></v-divider>
        <v-list-item
          prepend-icon="mdi-account-settings"
          title="Paramètres"
          to="/dashboard-conducteur/parametres-conducteur"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-logout"
          title="Déconnexion"
          @click="deconnexion"
        ></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- Contenu principal avec overlay pour mobile -->
    <v-main class="pa-4" :class="{ 'ml-260': permanent && !rail }">
      <router-view />
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useDisplay } from 'vuetify';
import { useRouter } from 'vue-router';
import axios from 'axios';

const routeur = useRouter();
const display = useDisplay();
const drawer = ref(true);
const rail = ref(false); // Mode rail (slim) optionnel
const permanent = computed(() => display.mdAndUp && !rail.value); // Permanent sur desktop sauf en mode rail
const nom = ref('');

// Données simulées pour les statistiques
const gainTotal = ref(450); // À récupérer via API
const contratsActifs = ref(['Contrat 1', 'Contrat 2']); // À récupérer via API
const candidaturesEnAttente = ref(['Candidature 1']); // À récupérer via API

const chargerInfosUtilisateur = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    routeur.push('/');
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8000/get_utilisateur_infos_conducteur.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      nom.value = reponse.data.user.nom;
    } else {
      routeur.push('/');
    }
  } catch (erreur) {
    console.error('Erreur chargement infos:', erreur);
    routeur.push('/');
  }
};

onMounted(chargerInfosUtilisateur);


const deconnexion = () => {
  localStorage.clear();
  alert('Déconnexion réussie');
  routeur.push('/');
};
</script>

<style scoped>
.animated-item {
  transition: transform 0.3s ease;
}
.animated-item:hover {
  transform: translateX(5px);
}

@media (max-width: 959px) {
  .v-main {
    padding: 16px !important;
  }
}

@media (min-width: 960px) {
  .ml-260 {
    margin-left: 260px !important; /* Pousse le contenu à côté du drawer */
  }
}
</style>