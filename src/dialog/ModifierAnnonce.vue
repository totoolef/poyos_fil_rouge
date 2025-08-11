<template>
  <v-card>
    <v-card-title class="headline pa-4">
      <h2 class="mb-0">Modifier l'annonce</h2>
    </v-card-title>
    <v-card-text class="pa-4">
      <v-alert v-if="!formulaire" type="info" class="mb-4">Aucune annonce sélectionnée pour modification.</v-alert>
      <v-form v-else ref="form" v-model="formulaireValide" @submit.prevent="soumettreModifications" :disabled="!formulaire">
        <v-select
          v-model="formulaire.typePub"
          :items="typesPub"
          item-title="libelle"
          item-value="id"
          label="Type de pub"
          variant="outlined"
          :rules="[regles.obligatoire]"
          required
        ></v-select>
        <v-text-field
          v-model="formulaire.titre"
          label="Titre"
          variant="outlined"
          :rules="[regles.obligatoire]"
          required
        ></v-text-field>
        <v-textarea
          v-model="formulaire.description"
          label="Description"
          variant="outlined"
          :rules="[regles.obligatoire]"
          required
        ></v-textarea>
        <v-text-field
          v-model="formulaire.localisation"
          label="Localisation"
          variant="outlined"
          :rules="[regles.obligatoire]"
          required
        ></v-text-field>
        <v-text-field
          v-model="formulaire.nombreVehicules"
          label="Nombre de véhicules"
          type="number"
          variant="outlined"
          min="1"
          :rules="[regles.obligatoire]"
          required
        ></v-text-field>
        <v-text-field
          v-model="formulaire.dureeMois"
          label="Durée (mois)"
          type="number"
          variant="outlined"
          min="1"
          :rules="[regles.obligatoire]"
          required
        ></v-text-field>
        <v-text-field
          v-model="formulaire.paiementMensuel"
          label="Paiement mensuel (€)"
          type="number"
          variant="outlined"
          min="50"
          :rules="[regles.obligatoire]"
          required
        ></v-text-field>
        <v-select
          v-model="formulaire.statut"
          :items="statuts"
          label="Statut"
          variant="outlined"
          :rules="[regles.obligatoire]"
          required
        ></v-select>
        <v-card-actions class="pa-0 mt-4">
          <v-btn
            color="primary"
            type="submit"
            :loading="enCoursDeSoumission"
            :disabled="!formulaireValide || !formulaire"
            class="mr-4"
          >
            Enregistrer
          </v-btn>
          <v-btn color="red-lighten-1" @click="$emit('fermer')">Annuler</v-btn>
        </v-card-actions>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';

// Props avec typage nullable
const props = defineProps<{
  annonce: { id: number; type_pub: string; titre: string; description: string; localisation: string; nombre_vehicules: number; duree_mois: number; paiement_mensuel: number; statut: string; } | null;
}>();

const emit = defineEmits(['fermer', 'actualiser']);

// Formulaire réactif avec valeurs initiales basées sur la prop
const formulaire = ref<{
  typePub: string;
  titre: string;
  description: string;
  localisation: string;
  nombreVehicules: number;
  dureeMois: number;
  paiementMensuel: number;
  statut: string;
} | null>(null);
const formulaireValide = ref(false);
const enCoursDeSoumission = ref(false);

// Règles de validation
const regles = {
  obligatoire: (valeur: string | number) => !!valeur || 'Ce champ est requis',
};

// Types de pub (simulés, à ajuster si dynamique)
const typesPub = [
  { id: 'coffre', libelle: 'Logo sur le coffre' },
  { id: 'portieres', libelle: 'Logos sur les portières' },
  { id: 'toutes-portieres', libelle: 'Toutes les portières' },
  { id: 'toute-voiture', libelle: 'Toute la voiture' },
];

// Statuts (simulés)
const statuts = ['ouverte', 'en_cours', 'fermee'];

// Synchronisation avec la prop annonce
watch(() => props.annonce, (newAnnonce) => {
  if (newAnnonce) {
    formulaire.value = {
      typePub: newAnnonce.type_pub,
      titre: newAnnonce.titre,
      description: newAnnonce.description,
      localisation: newAnnonce.localisation,
      nombreVehicules: newAnnonce.nombre_vehicules,
      dureeMois: newAnnonce.duree_mois,
      paiementMensuel: newAnnonce.paiement_mensuel,
      statut: newAnnonce.statut,
    };
  } else {
    formulaire.value = null;
  }
}, { immediate: true });

// Soumission des modifications via Axios
const soumettreModifications = async () => {
  if (!formulaire.value) return; // Sécurité si null
  enCoursDeSoumission.value = true;
  try {
    const token = localStorage.getItem('token');
    const reponse = await axios.post('http://localhost:8000/modifier_annonce.php', {
      id: props.annonce?.id,
      type_pub: formulaire.value.typePub,
      titre: formulaire.value.titre,
      description: formulaire.value.description,
      localisation: formulaire.value.localisation,
      nombre_vehicules: formulaire.value.nombreVehicules,
      duree_mois: formulaire.value.dureeMois,
      paiement_mensuel: formulaire.value.paiementMensuel,
      statut: formulaire.value.statut,
    }, {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    });
    if (reponse.data.success) {
      emit('actualiser');
      emit('fermer');
      alert('Annonce modifiée avec succès !');
    } else {
      alert(reponse.data.message || 'Échec de la modification');
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