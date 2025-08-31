<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <h2 class="text-h6 mb-4">Créer une nouvelle annonce</h2>
      <v-stepper v-model="etape" alt-labels>
        <!-- Étape 1 : Choix du type de publicité -->
        <v-stepper-step :complete="etape > 1" step="1">Choisir le type de pub</v-stepper-step>
        <v-stepper-content step="1">
          <v-row>
            <v-col v-for="(type, index) in typesPub" :key="index" cols="12" md="3">
              <v-card
                :color="annonce.type === type.id ? 'primary' : 'grey-lighten-4'"
                class="pa-4 text-center cursor-pointer"
                @click="selectionnerType(type)"
                outlined
                :elevation="annonce.type === type.id ? 4 : 1"
              >
                <v-icon size="x-large" class="mb-2" :color="annonce.type === type.id ? 'white' : 'primary'">{{ type.icone }}</v-icon>
                <h3 class="text-subtitle-1 mb-2">{{ type.libelle }}</h3>
                <p class="text-caption mb-2">{{ type.description }}</p>
                <v-chip color="success" variant="flat" size="small">
                  Estimation : {{ type.estimation }} €/mois
                </v-chip>
              </v-card>
            </v-col>
          </v-row>
        </v-stepper-content>

        <!-- Étape 2 : Détails de l'annonce (affiché seulement après sélection) -->
        <v-stepper-content step="2">
          <v-form v-if="annonce.type">
            <v-text-field
              v-model="annonce.titre"
              label="Titre de l'annonce"
              variant="outlined"
              color="primary"
              class="mb-4"
              required
            ></v-text-field>
            <v-textarea
              v-model="annonce.description"
              label="Description détaillée"
              variant="outlined"
              color="primary"
              class="mb-4"
              required
            ></v-textarea>
            <v-select
              v-model="annonce.localisationType"
              :items="typesLocalisation"
              item-title="affichage"
              item-value="valeur"
              label="Type de localisation"
              variant="outlined"
              class="mb-4"
              required
            ></v-select>
            <v-text-field
              v-if="annonce.localisationType === 'personnalise'"
              v-model="annonce.localisationPersonnalisee"
              label="Localisation personnalisée"
              variant="outlined"
              color="primary"
              class="mb-4"
              required
            ></v-text-field>
            <v-row v-if="annonce.localisationType === 'precis'">
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="annonce.codePostal"
                  label="Code postal"
                  variant="outlined"
                  placeholder="Entrez votre code postal"
                  :rules="[regles.obligatoire, regles.codePostalValide]"
                  @update:model-value="rechercheDebounceeCodePostal"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-autocomplete
                  v-model="annonce.villeSelectionnee"
                  :items="suggestionsVilles"
                  :disabled="!annonce.codePostal"
                  item-title="nom"
                  item-value="nom"
                  label="Ville"
                  variant="outlined"
                  placeholder="Sélectionnez une ville"
                  clearable
                  :rules="[regles.obligatoire]"
                  required
                ></v-autocomplete>
              </v-col>
            </v-row>
            <v-text-field
              v-model="annonce.nombreVehicules"
              label="Nombre de véhicules recherchés"
              type="number"
              variant="outlined"
              color="primary"
              class="mb-4"
              min="1"
              required
            ></v-text-field>
            <v-text-field
              v-model="annonce.dureeMois"
              label="Durée du contrat (mois)"
              type="number"
              variant="outlined"
              color="primary"
              class="mb-4"
              min="1"
              required
            ></v-text-field>
            <v-text-field
              v-model="annonce.paiementMensuel"
              label="Paiement mensuel proposé (€)"
              type="number"
              variant="outlined"
              color="primary"
              class="mb-4"
              :min="estimationBase"
              required
            ></v-text-field>
          </v-form>
        </v-stepper-content>

        <!-- Étape 3 : Estimation et publication (adaptée au type sélectionné) -->
        <v-stepper-step step="3">Estimation et publication</v-stepper-step>
        <v-stepper-content step="3">
          <v-alert type="info" variant="tonal" class="mb-4">
            Estimation mensuelle base : {{ estimationBase }} €<br>
            Estimation totale : {{ estimationTotale }} € pour {{ annonce.dureeMois }} mois<br>
            Frais (12%) : {{ commission }} €<br>
            Paiement au particulier : {{ paiementParticulier }} €
          </v-alert>
          <v-btn color="primary" :loading="enCoursDePublication" @click="publierAnnonce">Publier l'annonce</v-btn>
          <v-btn text class="ml-2" @click="etape = 2">Retour</v-btn>
        </v-stepper-content>
      </v-stepper>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { debounce } from 'lodash';

// Interface pour les villes
interface Ville {
  nom: string;
  code: string;
  codesPostaux: string[];
}

// Types de localisation
const typesLocalisation = [
  { valeur: 'personnalise', affichage: 'Personnalisée' },
  { valeur: 'precis', affichage: 'Précis (recommandé pour business local)' },
];

const routeur = useRouter();
const etape = ref(1);
const enCoursDePublication = ref(false);

// Interface pour les types de pub
interface TypePub {
  id: string;
  libelle: string;
  description: string;
  estimation: number;
  icone: string;
}

const annonce = ref({
  type: '',
  titre: '',
  description: '',
  localisationType: 'precis', // Défaut à 'precis'
  localisationPersonnalisee: '',
  codePostal: '',
  villeSelectionnee: null as Ville | null,
  localisation: '',
  nombreVehicules: 1,
  dureeMois: 1,
  paiementMensuel: 0,
});
const suggestionsVilles = ref<Ville[]>([]);
const estChargement = ref(false);
const typesPub: TypePub[] = [
  { id: 'coffre', libelle: 'Logo sur le coffre', description: 'Visibilité arrière modérée', estimation: 50, icone: 'mdi-car-back' },
  { id: 'portieres', libelle: 'Logos sur les portières', description: 'Visibilité latérale bonne', estimation: 100, icone: 'mdi-car-door' },
  { id: 'toutes-portieres', libelle: 'Toutes les portières', description: 'Visibilité maximale latérale', estimation: 150, icone: 'mdi-car-side' },
  { id: 'toute-voiture', libelle: 'Toute la voiture', description: 'Wrapping complet, impact total', estimation: 250, icone: 'mdi-car' },
];

// Règles de validation
const regles = {
  obligatoire: (valeur: string | number) => !!valeur || 'Ce champ est requis',
  codePostalValide: (valeur: string) => /^[0-9]{5}$/.test(valeur) || 'Le code postal doit contenir 5 chiffres',
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

// Mise à jour de localisation combinée
watch([() => annonce.value.localisationType, () => annonce.value.villeSelectionnee, () => annonce.value.localisationPersonnalisee, () => annonce.value.codePostal], () => {
  if (annonce.value.localisationType === 'precis' && annonce.value.villeSelectionnee) {
    annonce.value.localisation = annonce.value.villeSelectionnee.nom + ', ' + annonce.value.codePostal;
  } else if (annonce.value.localisationType === 'personnalise') {
    annonce.value.localisation = annonce.value.localisationPersonnalisee;
  } else {
    annonce.value.localisation = '';
  }
});

// Sélection du type de pub
const selectionnerType = (type: { id: string; estimation: number; }) => {
  annonce.value.type = type.id;
  annonce.value.paiementMensuel = type.estimation;
};

// Estimation base du type sélectionné
const estimationBase = computed(() => {
  const typeSelectionne = typesPub.find(t => t.id === annonce.value.type);
  return typeSelectionne ? typeSelectionne.estimation : 0;
});

// Calculs d'estimations adaptés
const estimationTotale = computed(() => annonce.value.dureeMois * annonce.value.paiementMensuel);
const commission = computed(() => estimationTotale.value * 0.12);
const paiementParticulier = computed(() => estimationTotale.value - commission.value);

const publierAnnonce = async () => {
  enCoursDePublication.value = true;
  try {
    const token = localStorage.getItem('token');
    const reponse = await axios.post('http://localhost:8080/annonces/creer_annonce.php', {
      type_pub: annonce.value.type,
      titre: annonce.value.titre,
      description: annonce.value.description,
      localisation_type: annonce.value.localisationType,
      localisation_personnalisee: annonce.value.localisationPersonnalisee,
      code_postal: annonce.value.codePostal,
      ville: annonce.value.villeSelectionnee?.nom || '',
      localisation: annonce.value.localisation,
      nombre_vehicules: annonce.value.nombreVehicules,
      duree_mois: annonce.value.dureeMois,
      paiement_mensuel: annonce.value.paiementMensuel,
    }, {
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    });
    if (reponse.data.success) {
      alert('Annonce publiée avec succès !');
      routeur.push('/dashboard-annonceur/annonces-postees');
    } else {
      alert(reponse.data.message || 'Échec de la publication');
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    alert('Erreur de connexion au serveur');
  } finally {
    enCoursDePublication.value = false;
  }
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>