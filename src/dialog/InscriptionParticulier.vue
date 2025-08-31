<template>
  <v-card>
    <v-card-title class="headline pa-4">
      <h2 class="mb-0">Inscription Conducteur</h2>
    </v-card-title>
    <v-card-text class="pa-4">
      <v-form ref="form" v-model="formulaireValide" @submit.prevent="soumettreInscription">
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.email"
              label="Email"
              variant="outlined"
              :rules="[regles.obligatoire, regles.emailValide]"
              required
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.motdepasse"
              label="Mot de passe"
              type="password"
              variant="outlined"
              :rules="[regles.obligatoire, regles.motDePasseValide]"
              required
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
              v-model="formulaire.dateNaissance"
              label="Date de naissance"
              type="date"
              variant="outlined"
              :rules="[regles.obligatoire, regles.dateValide]"
              required
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.codePostal"
              label="Code postal"
              variant="outlined"
              placeholder="Entrez votre code postal"
              :rules="[regles.obligatoire, regles.codePostalValide]"
              @update:model-value="rechercheDebounceeCodePostal"
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row>
          <v-col cols="12" md="6">
            <v-autocomplete
              v-model="formulaire.villeSelectionnee"
              :items="suggestionsVilles"
              :disabled="!formulaire.codePostal"
              item-title="nom"
              item-value="code"
              return-object
              label="Ville"
              variant="outlined"
              placeholder="Sélectionnez une ville"
              clearable
              :rules="[regles.obligatoire]"
              required
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="formulaire.plaqueImmatriculation"
              label="Plaque d'immatriculation"
              variant="outlined"
              :rules="[regles.obligatoire, regles.plaqueValide]"
              required
            ></v-text-field>
          </v-col>
        </v-row>
        <v-card-actions class="pa-0 mt-4">
          <v-btn
            color="primary"
            type="submit"
            :loading="enCoursDeSoumission"
            :disabled="!formulaireValide"
            class="mr-4"
          >
            Créer mon compte
          </v-btn>
          <v-btn color="red-lighten-1" @click="fermerDialog">Fermer</v-btn>
        </v-card-actions>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { debounce } from 'lodash';
import axios from 'axios';
import router from '../router';
// Interfaces
interface Ville {
  nom: string;
  code: string;
  codesPostaux: string[];
}

interface Formulaire {
  email: string;
  motdepasse: string;
  nom: string;
  prenom: string;
  dateNaissance: string;
  codePostal: string;
  villeSelectionnee: Ville | null;
  plaqueImmatriculation: string;
}

// État du formulaire
const formulaire = ref<Formulaire>({
  email: '',
  motdepasse: '',
  nom: '',
  prenom: '',
  dateNaissance: '',
  codePostal: '',
  villeSelectionnee: null,
  plaqueImmatriculation: '',
});
const formulaireValide = ref(false);
const enCoursDeSoumission = ref(false);
const suggestionsVilles = ref<Ville[]>([]);
const estChargement = ref(false);
const emit = defineEmits(['fermerDialog']);

// Règles de validation
const regles = {
  obligatoire: (valeur: string | null) => !!valeur || 'Ce champ est requis',
  emailValide: (valeur: string) => /.+@.+\..+/.test(valeur) || 'Email invalide',
  motDePasseValide: (valeur: string) => (valeur?.length || 0) >= 6 || 'Minimum 6 caractères',
  dateValide: (valeur: string) => !valeur || !isNaN(new Date(valeur).getTime()) || 'Date invalide',
  codePostalValide: (valeur: string) => /^[0-9]{5}$/.test(valeur) || 'Le code postal doit contenir 5 chiffres',
  plaqueValide: (valeur: string) => /^[A-Z]{2}-[0-9]{3}-[A-Z]{2}$/.test(valeur) || 'Format plaque : AA-123-AA',
};

// Recherche des villes par code postal
const rechercherVillesParCodePostal = async (code: string) => {
  if (!code || code.length < 3) {
    suggestionsVilles.value = [];
    return;
  }
  estChargement.value = true;
  try {
    const response = await fetch(`https://geo.api.gouv.fr/communes?codePostal=${encodeURIComponent(code)}&fields=nom,code,codesPostaux&limit=10`);
    if (!response.ok) throw new Error('Erreur réseau');
    suggestionsVilles.value = await response.json();
  } catch (error) {
    console.error('Erreur lors de la recherche des villes:', error);
    suggestionsVilles.value = [];
  } finally {
    estChargement.value = false;
  }
};

// Débouncer la recherche
const rechercheDebounceeCodePostal = debounce(rechercherVillesParCodePostal, 300);

// Réinitialiser la ville quand le code postal change
watch(() => formulaire.value.codePostal, (nouveauCode) => {
  formulaire.value.villeSelectionnee = null;
  if (nouveauCode && /^[0-9]{5}$/.test(nouveauCode)) {
    rechercherVillesParCodePostal(nouveauCode);
  } else {
    suggestionsVilles.value = [];
  }
});

// Soumission du formulaire via Axios
const soumettreInscription = async () => {
  if (!formulaire.value.villeSelectionnee) {
    alert('Veuillez sélectionner une ville');
    return;
  }
  enCoursDeSoumission.value = true;
  try {
    const reponse = await axios
    .post('http://localhost:8080/authentification/inscription_particulier.php', {
      email: formulaire.value.email,
      mot_de_passe: formulaire.value.motdepasse,
      nom: formulaire.value.nom,
      prenom: formulaire.value.prenom,
      date_naissance: formulaire.value.dateNaissance,
      code_postal: formulaire.value.codePostal,
      ville: formulaire.value.villeSelectionnee.nom,
      plaque_immatriculation: formulaire.value.plaqueImmatriculation,
      role: 'conducteur',
    }, {
      headers: { 'Content-Type': 'application/json' },
    });
    if (reponse.data.success) {
      alert('Inscription réussie !');
      fermerDialog();

      router.push('/dashboard-conducteur/annonces-disponibles')
    }
  } catch (erreur) {
    console.error(erreur);
  } finally {
    enCoursDeSoumission.value = false;
  }
};

const fermerDialog = () => {
  emit('fermerDialog');
};
</script>