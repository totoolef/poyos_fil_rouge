<template>
  <v-card>
    <v-card-title class="headline pa-4">
      <h2 class="mb-0">Candidater à l'annonce</h2>
    </v-card-title>
    <v-card-text class="pa-4">
      <v-form ref="form" v-model="formulaireValide" @submit.prevent="soumettreCandidature">
        <v-textarea
          v-model="formulaire.message"
          label="Message de candidature"
          variant="outlined"
          :rules="[regles.obligatoire]"
          required
        ></v-textarea>

        <v-select
          v-model="formulaire.marqueVoiture"
          :items="marquesVoiture"
          label="Marque de votre voiture"
          variant="outlined"
          :rules="[regles.obligatoire]"
          @update:model-value="mettreAJourModeles"
          clearable
          :custom-filter="filtrer"
          hide-no-data
        ></v-select>


        <v-select
          v-model="formulaire.modeleVoiture"
          :items="modelesFiltres"
          label="Modèle de votre voiture"
          variant="outlined"
          :rules="[regles.obligatoire]"
          :disabled="!formulaire.marqueVoiture"
          clearable
          filter
          autocomplete
          item-title=""
          item-value=""
        ></v-select>


        <v-select
          v-model="formulaire.couleur"
          :items="couleurs"
          label="Couleur de votre voiture"
          variant="outlined"
          :rules="[regles.obligatoire]"
          required
        ></v-select>

        <v-card-actions class="pa-0 mt-4">
          <v-btn
            color="primary"
            type="submit"
            :loading="enCoursDeSoumission"
            :disabled="!formulaireValide"
            class="mr-4"
          >
            Candidater
          </v-btn>
          <v-btn color="red-lighten-1" @click="$emit('close')">Annuler</v-btn>
        </v-card-actions>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';
import marquesModelesJson from '../assets/marques_modeles_nettoyes.json';

// Définir le type du JSON
const marquesModeles = marquesModelesJson as Record<string, string[]>;

const props = defineProps<{ annonceId: number }>();
const emit = defineEmits(['close', 'actualiser']);

const formulaire = ref({
  message: '',
  marqueVoiture: '',
  modeleVoiture: '',
  couleur: '',
});
const formulaireValide = ref(false);
const enCoursDeSoumission = ref(false);

// Validation
const regles = {
  obligatoire: (val: string) => !!val || 'Ce champ est requis',
};

// Marques et modèles
const marquesVoiture = Object.keys(marquesModeles).sort();
const modelesFiltres = ref<string[]>([]);

// Couleurs disponibles
const couleurs = ['Noir', 'Blanc', 'Gris', 'Rouge', 'Bleu', 'Autre'];

// Met à jour les modèles selon la marque choisie
const mettreAJourModeles = (marque: string) => {
  modelesFiltres.value = marquesModeles[marque] ?? [];
  formulaire.value.modeleVoiture = '';
};

const filtrer = (item: string, query: string) => {
  return item.toLowerCase().includes(query.toLowerCase());
};

// Soumission
const soumettreCandidature = async () => {
  enCoursDeSoumission.value = true;
  try {
    const token = localStorage.getItem('token');
    const reponse = await axios.post('http://localhost:8000/candidater_annonce.php', {
      annonce_id: props.annonceId,
      message: formulaire.value.message,
      marque_voiture: formulaire.value.marqueVoiture,
      modele_voiture: formulaire.value.modeleVoiture,
      couleur: formulaire.value.couleur,
    }, {
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    });

    if (reponse.data.success) {
      alert('Candidature envoyée avec succès !');
      emit('actualiser');
      emit('close');
    } else {
      alert(reponse.data.message || 'Échec de la candidature');
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    alert('Erreur de connexion au serveur');
  } finally {
    enCoursDeSoumission.value = false;
  }
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
