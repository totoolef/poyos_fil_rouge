<template>
  <v-container fluid class="fill-height pa-0">
    <v-row class="align-center" no-gutters>
      <!-- Colonne du formulaire (mobile et desktop) -->
      <v-col cols="12" md="6" class="pa-4 pa-md-8">
        <v-card class="elevation-3 pa-6 rounded-lg">
          <v-card-title class="text-h5 text-center mb-6">Connexion</v-card-title>
          <v-divider class="mb-6"></v-divider>
          <v-card-text class="text-grey text-center mb-4">Accédez à votre espace Poyos</v-card-text>
          <v-form ref="form" @submit.prevent="connexion">
            <v-text-field
              v-model="formulaire.email"
              label="Email"
              type="email"
              variant="outlined"
              color="primary"
              class="mb-4"
              :rules="[regles.obligatoire, regles.emailValide]"
              required
            ></v-text-field>
            <v-text-field
              v-model="formulaire.motDePasse"
              label="Mot de passe"
              type="password"
              variant="outlined"
              color="primary"
              class="mb-6"
              :rules="[regles.obligatoire]"
              required
            ></v-text-field>
            <v-radio-group v-model="formulaire.role" class="mb-6" inline :rules="[regles.obligatoire]">
              <v-radio label="Conducteur" value="conducteur"></v-radio>
              <v-radio label="Annonceur" value="annonceur"></v-radio>
            </v-radio-group>
            <v-btn
              color="primary"
              block
              rounded
              size="large"
              type="submit"
              :loading="enCoursDeConnexion"
              class="text-none"
            >
              Se connecter
            </v-btn>
            <v-progress-linear
              v-if="enCoursDeConnexion"
              indeterminate
              color="primary"
              class="mt-4"
            ></v-progress-linear>
            <v-alert v-if="erreurConnexion" type="error" class="mt-4">{{ erreurConnexion }}</v-alert>
          </v-form>
        </v-card>
      </v-col>

      <!-- Colonne décorative innovante (mobile et desktop) -->
      <v-col
        cols="12"
        md="6"
        class="pa-4 pa-md-8 d-flex align-center justify-center decor-section"
      >
        <div class="text-center text-white">
          <v-icon size="x-large" color="white" class="mb-4 animated-icon">mdi-rocket-launch</v-icon>
          <h2 class="text-h4 mb-4" style="text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.3);">
            Conduisez, annoncez, <br> prospérez avec Poyos
          </h2>
          <p class="text-body-1 mb-6">Rejoignez une communauté innovante où chaque trajet devient une opportunité rentable.</p>
          <v-btn color="white" variant="outlined" rounded @click="routeur.push('/inscription')">S'inscrire maintenant</v-btn>
        </div>
      </v-col>
    </v-row>

    <v-footer app color="indigo-lighten-1" class="text-center py-4">
      <div class="d-flex justify-center mb-2">
        <v-btn
          v-for="lien in liensSociaux"
          :key="lien.icone"
          :icon="lien.icone"
          :href="lien.lien"
          target="_blank"
          density="comfortable"
          variant="text"
        ></v-btn>
      </div>
      <v-divider class="my-2 w-25 mx-auto"></v-divider>
      <div class="text-caption">
        © {{ new Date().getFullYear() }} — <strong>Poyos</strong>. Tous droits réservés.
      </div>
    </v-footer>
  </v-container>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '../stores/utilisateurStores';
import axios from 'axios';

const routeur = useRouter();
const userStore = useUserStore();
const enCoursDeConnexion = ref(false);
const erreurConnexion = ref<string | null>(null);
const formulaire = ref({
  email: '',
  motDePasse: '',
  role: 'conducteur',
});

// Règles de validation simples
const regles = {
  obligatoire: (valeur: string) => !!valeur || 'Ce champ est requis',
  emailValide: (valeur: string) => /.+@.+\..+/.test(valeur) || 'Email invalide',
};

// Fonction de connexion
const connexion = async () => {
  if (!formulaire.value.email || !formulaire.value.motDePasse || !formulaire.value.role) {
    erreurConnexion.value = 'Veuillez remplir tous les champs';
    return;
  }
  enCoursDeConnexion.value = true;
  erreurConnexion.value = null;
  try {
    console.log('Données envoyées:', formulaire.value);
    const reponse = await axios.post('http://localhost:8080/authentification/connexion.php', {
      email: formulaire.value.email,
      mot_de_passe: formulaire.value.motDePasse,
      role: formulaire.value.role,
    }, {
      headers: { 'Content-Type': 'application/json' },
    });
    console.log('Réponse reçue:', reponse.data);
    if (reponse.data.success) {
      // Utiliser le store pour mettre à jour l'état d'authentification
      userStore.login({
        role: reponse.data.role,
        token: reponse.data.token,
        id: reponse.data.id || '0'
      });
      
      // Redirection sera gérée par le router guard
      if (reponse.data.role === 'conducteur') {
        routeur.push('/dashboard-conducteur/campagnes');
      } else if (reponse.data.role === 'annonceur') {
        routeur.push('/dashboard-annonceur');
      } else {
        erreurConnexion.value = 'Rôle non reconnu';
      }
    } else {
      erreurConnexion.value = reponse.data.message || 'Échec de la connexion';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    erreurConnexion.value = 'Erreur de connexion au serveur';
  } finally {
    enCoursDeConnexion.value = false;
  }
};

const liensSociaux = [
  { icone: 'mdi-facebook', lien: 'https://www.facebook.com' },
  { icone: 'mdi-twitter', lien: 'https://www.twitter.com' },
  { icone: 'mdi-linkedin', lien: 'https://www.linkedin.com' },
  { icone: 'mdi-instagram', lien: 'https://www.instagram.com' },
];
</script>

<style scoped>
.page {
  min-height: 100vh;
}

.decor-section {
  background: radial-gradient(circle at top left, #1e40af, #3b82f6, #60a5fa);
  position: relative;
  overflow: hidden;
}

.decor-section::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.2), transparent);
  opacity: 0.3;
  animation: pulse 8s infinite ease-in-out;
}

.animated-icon {
  animation: launch 2s ease-in-out infinite;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

@keyframes launch {
  0% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
  100% { transform: translateY(0); }
}

@media (min-width: 960px) {
  .v-col {
    padding: 16px;
  }

  .v-footer {
    position: absolute;
    bottom: 0;
    width: 100%;
  }
}
</style>