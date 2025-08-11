<template>
  <v-app id="dashboard-layout">
    <v-app-bar app color="indigo-lighten-2" flat>
      <!-- Logo et titre avec nom et entreprise -->
      <v-toolbar-title class="text-white">
        Dashboard Commerçant - {{ nom }} ({{ nomEntreprise }})
      </v-toolbar-title>
      <v-spacer></v-spacer>
      <!-- Icône pour ouvrir le drawer sur mobile -->
      <v-app-bar-nav-icon class="d-flex d-md-none" @click="drawer = !drawer"></v-app-bar-nav-icon>
    </v-app-bar>

    <!-- Navigation Drawer latéral -->
    <v-navigation-drawer
      v-model="drawer"
      :permanent="permanent"
      app
      color="grey-lighten-4"
      width="250"
      class="elevation-2"
    >
      <v-list nav>
        <v-list-item
          prepend-icon="mdi-bullhorn"
          title="Annonces postées"
          to="/dashboard-annonceur/annonces-postees"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-account-multiple-check"
          title="Candidatures reçues"
          to="/dashboard-annonceur/candidatures"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-file-document"
          title="Contrats en cours"
          to="/dashboard-annonceur/contrats"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-currency-usd"
          title="Paiements et commissions"
          to="/dashboard-annonceur/paiements"
        ></v-list-item>
        <v-divider class="my-2"></v-divider>
        <v-list-item
          prepend-icon="mdi-pencil"
          title="Créer une annonce"
          to="/dashboard-annonceur/creer-annonce"
        ></v-list-item>
        <v-divider class="my-2"></v-divider>
        <v-list-item
          prepend-icon="mdi-account-settings"
          title="Paramètres"
          to="/dashboard-annonceur/parametres-annonceur"
        ></v-list-item>
        <v-list-item
          prepend-icon="mdi-logout"
          title="Déconnexion"
          @click="deconnexion"
        ></v-list-item>
      </v-list>
    </v-navigation-drawer>

    <!-- Contenu principal (poussé à droite du drawer sur desktop) -->
    <v-main class="pa-4">
      <router-view />
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useDisplay } from 'vuetify'; // Pour détecter la taille d'écran
import { useRouter } from 'vue-router';
import axios from 'axios';

const routeur = useRouter();
const display = useDisplay();
const drawer = ref(true); // Ouvert par défaut
const permanent = ref(display.mdAndUp); // Permanent sur desktop (md et plus)

const nom = ref('');
const nomEntreprise = ref('');

const chargerInfosUtilisateur = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    routeur.push('/');
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8000/get_utilisateur_infos.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      nom.value = reponse.data.user.nom;
      nomEntreprise.value = reponse.data.user.nom_entreprise || 'Entreprise non spécifiée';
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
/* Ajustements pour mobile first */
@media (max-width: 959px) {
  .v-main {
    padding: 16px !important; /* Padding réduit sur mobile */
  }
}

/* Forcer le push du contenu sur desktop */
@media (min-width: 960px) {
  .v-main {
    padding-left: 250px !important; /* Largeur du drawer */
  }
}
</style>