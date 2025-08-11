<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <h2 class="text-h6 mb-4">Paramètres de votre compte</h2>
      <v-progress-linear v-if="enChargement" indeterminate color="primary" class="mb-4"></v-progress-linear>
      <v-alert v-if="messageErreur" type="error" class="mb-4">{{ messageErreur }}</v-alert>
      <v-form v-else @submit.prevent="soumettreModifications">
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.email"
              label="Email"
              type="email"
              variant="outlined"
              :rules="[regles.obligatoire, regles.emailValide]"
              required
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.motDePasse"
              label="Nouveau mot de passe"
              type="password"
              variant="outlined"
              :rules="[regles.motDePasseValide]"
            ></v-text-field>
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
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.prenom"
              label="Prénom"
              variant="outlined"
              :rules="[regles.obligatoire]"
              required
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.nomEntreprise"
              label="Nom de l'entreprise"
              variant="outlined"
              :rules="[regles.obligatoire]"
              required
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <!-- Champ téléphone retiré -->
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
            ></v-text-field>
          </v-col>
        </v-row>
        <v-btn
          color="primary"
          type="submit"
          :loading="enCoursDeSoumission"
          class="mt-4"
        >
          Enregistrer les modifications
        </v-btn>
        <v-alert v-if="messageSucces" type="success" class="mt-4">{{ messageSucces }}</v-alert>
        <v-alert v-if="messageErreur" type="error" class="mt-4">{{ messageErreur }}</v-alert>
      </v-form>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';

// Interface mise à jour sans telephone
interface Formulaire {
  email: string;
  motDePasse: string;
  nom: string;
  prenom: string;
  nomEntreprise: string;
  adresse: string;
}

// État du formulaire
const formulaire = ref<Formulaire>({
  email: '',
  motDePasse: '',
  nom: '',
  prenom: '',
  nomEntreprise: '',
  adresse: '',
});
const enCoursDeSoumission = ref(false);
const enChargement = ref(true); // Pour le chargement initial
const messageSucces = ref<string | null>(null);
const messageErreur = ref<string | null>(null);
const token = localStorage.getItem('token');

// Règles de validation
const regles = {
  obligatoire: (valeur: string) => !!valeur || 'Ce champ est requis',
  emailValide: (valeur: string) => /.+@.+\..+/.test(valeur) || 'Email invalide',
  motDePasseValide: (valeur: string) => !valeur || valeur.length >= 6 || 'Minimum 6 caractères',
};

// Chargement des données utilisateur depuis la BDD au montage
onMounted(async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter';
    enChargement.value = false;
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8000/get_utilisateur_infos.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      const user = reponse.data.user;
      formulaire.value.email = user.email;
      formulaire.value.nom = user.nom;
      formulaire.value.prenom = user.prenom;
      formulaire.value.nomEntreprise = user.nom_entreprise || '';
      formulaire.value.adresse = user.adresse || '';
    } else {
      messageErreur.value = reponse.data.message || 'Échec du chargement des données';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  } finally {
    enChargement.value = false;
  }
});

// Soumission des modifications via Axios
const soumettreModifications = async () => {
  enCoursDeSoumission.value = true;
  messageSucces.value = null;
  messageErreur.value = null;
  try {
    const reponse = await axios.post('http://localhost:8000/modifier_parametres_annonceur.php', {
      id: localStorage.getItem('userId'), // Supposé stocké après connexion
      email: formulaire.value.email,
      mot_de_passe: formulaire.value.motDePasse, // Seulement si modifié
      nom: formulaire.value.nom,
      prenom: formulaire.value.prenom,
      nom_entreprise: formulaire.value.nomEntreprise,
      adresse: formulaire.value.adresse,
    }, {
      headers: { 
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
    });
    if (reponse.data.success) {
      messageSucces.value = 'Paramètres mis à jour avec succès !';
    } else {
      messageErreur.value = reponse.data.message || 'Échec de la mise à jour';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  } finally {
    enCoursDeSoumission.value = false;
  }
};
</script>