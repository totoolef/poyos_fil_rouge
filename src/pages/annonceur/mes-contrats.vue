<template>
  <v-container fluid class="pa-4 mt-8">
    <v-card class="elevation-3 rounded-lg pa-4">
      <h2 class="text-h6 mb-4">Mes contrats en cours</h2>
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
        :items="contratsFiltrees"
        :items-per-page="5"
        class="elevation-1 d-none d-md-table"
      >
        <template v-slot:item.statut="{ item }">
          <v-chip :color="getStatutColor(item.statut)" size="small">
            {{ item.statut }}
          </v-chip>
        </template>
        <template v-slot:item.actions="{ item }">
          <v-btn color="primary" variant="outlined" size="small" @click="telechargerContrat(item)">
            Télécharger PDF
          </v-btn>
          <v-btn v-if="item.statut === 'en_attente_signature'" color="success" variant="outlined" size="small" class="ml-2" @click="ouvrirDialogSignature(item)">
            Envoyer pour signature
          </v-btn>
        </template>
        <template v-slot:expanded-row="{ item }">
          <td colspan="6">
            <v-card class="pa-4 mt-2">
              <p><strong>Contenu :</strong> {{ item.contenu_contrat.substring(0, 200) }}...</p>
              <p><strong>Date début :</strong> {{ item.date_debut }}</p>
              <p><strong>Date signature :</strong> {{ item.signature_date || 'Non signé' }}</p>
            </v-card>
          </td>
        </template>
      </v-data-table>
      <v-row v-if="display.smAndDown" class="d-md-none">
        <v-col v-for="(contrat, index) in contratsFiltrees" :key="index" cols="12">
          <v-card class="elevation-2 pa-4 mb-4">
            <v-card-title>{{ contrat.titre_annonce }}</v-card-title>
            <v-card-subtitle>{{ contrat.nom_conducteur }} {{ contrat.prenom_conducteur }}</v-card-subtitle>
            <v-card-text>
              <p>Statut : <v-chip :color="getStatutColor(contrat.statut)" size="small">{{ contrat.statut }}</v-chip></p>
              <p>Date début : {{ contrat.date_debut }}</p>
              <p>Date signature : {{ contrat.signature_date || 'Non signé' }}</p>
            </v-card-text>
            <v-card-actions>
              <v-btn color="primary" variant="outlined" size="small" @click="telechargerContrat(contrat)">
                Télécharger PDF
              </v-btn>
              <v-btn v-if="contrat.statut === 'en_attente_signature'" color="success" variant="outlined" size="small" class="ml-2" @click="ouvrirDialogSignature(contrat)">
                Envoyer pour signature
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>
      <v-dialog v-model="dialogSignature" max-width="500">
        <v-card>
          <v-card-title class="headline pa-4">
            <h2 class="mb-0">Envoyer pour signature</h2>
          </v-card-title>
          <v-card-text class="pa-4">
            <v-file-input v-model="fichierPDF" label="Sélectionner le PDF généré" accept="application/pdf" :rules="[v => !!v || 'Fichier requis']" required></v-file-input>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-btn color="primary" :loading="enCoursEnvoi" :disabled="!fichierPDF" @click="envoyerPourSignature">Envoyer</v-btn>
            <v-spacer></v-spacer>
            <v-btn color="red-lighten-1" @click="dialogSignature = false">Annuler</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
      <v-alert v-if="messageErreur" type="error" class="mt-4">{{ messageErreur }}</v-alert>
      <v-alert v-if="!contrats.length && !enChargement" type="info" class="mt-4">Aucun contrat en cours.</v-alert>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import jsPDF from 'jspdf';
import { useDisplay } from 'vuetify';

const display = useDisplay();
const contrats = ref<Contrat[]>([]);
const filtreStatut = ref<string | null>(null);
const enChargement = ref(true);
const messageErreur = ref<string | null>(null);
const dialogSignature = ref(false);
const contratSelectionne = ref<Contrat | null>(null);
const fichierPDF = ref<File | null>(null);
const enCoursEnvoi = ref(false);

interface Contrat {
  id: number;
  annonce_id: number;
  conducteur_id: number;
  contenu_contrat: string;
  date_debut: string | null;
  statut: string;
  created_at: string;
  updated_at: string;
  signature_date: string | null;
  contrat_pdf_url: string | null;
  titre_annonce: string | null;
  nom_conducteur: string | null;
  prenom_conducteur: string | null;
}

const entetes = [
  { title: 'Titre annonce', key: 'titre_annonce' },
  { title: 'Conducteur', key: 'conducteur', sortable: false },
  { title: 'Statut', key: 'statut' },
  { title: 'Date début', key: 'date_debut' },
  { title: 'Actions', key: 'actions', sortable: false },
] as const;

const statutsFiltres = ['toutes', 'en_attente_signature', 'actif', 'signé'];

const contratsFiltrees = computed(() => {
  if (!filtreStatut.value || filtreStatut.value === 'toutes') {
    return contrats.value;
  }
  return contrats.value.filter(contrat => contrat.statut === filtreStatut.value);
});

const getStatutColor = (statut: string) => {
  switch (statut) {
    case 'en_attente_signature': return 'warning';
    case 'actif': return 'success';
    case 'signé': return 'primary';
    default: return 'grey';
  }
};

// Fonction pour charger les contrats
const chargerContrats = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter';
    enChargement.value = false;
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8000/get_contrats.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      contrats.value = reponse.data.contrats as Contrat[];
    } else {
      messageErreur.value = reponse.data.message || 'Échec du chargement des contrats';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  } finally {
    enChargement.value = false;
  }
};

onMounted(chargerContrats);

const telechargerContrat = (contrat: Contrat) => {
  if (contrat.contenu_contrat) {
    const doc = new jsPDF();
    doc.setFontSize(11);
    doc.setFont("helvetica", "normal");
    doc.text(contrat.contenu_contrat, 20, 20, { maxWidth: 160 });
    doc.save(`contrat_${contrat.id}.pdf`);
  } else {
    messageErreur.value = 'Contenu du contrat non disponible';
  }
};

const ouvrirDialogSignature = (contrat: Contrat) => {
  contratSelectionne.value = contrat;
  dialogSignature.value = true;
  fichierPDF.value = null; // Réinitialise le fichier
};

const envoyerPourSignature = async () => {
  if (!contratSelectionne.value || !fichierPDF.value) {
    messageErreur.value = 'Veuillez sélectionner un fichier PDF';
    return;
  }

  enCoursEnvoi.value = true;
  const formData = new FormData();
  formData.append('file', fichierPDF.value);
  formData.append('contract_id', contratSelectionne.value.id.toString());

  try {
    const token = localStorage.getItem('token');
    const reponse = await axios.post('http://localhost:8000/envoyer_signature.php', formData, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'multipart/form-data',
      },
    });
    if (reponse.data.success) {
      alert('Contrat envoyé pour signature avec succès !');
      dialogSignature.value = false;
      chargerContrats(); // Rafraîchir la liste
    } else {
      messageErreur.value = reponse.data.message || 'Échec de l\'envoi';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  } finally {
    enCoursEnvoi.value = false;
  }
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
.animated-card {
  transition: transform 0.3s;
}
.animated-card:hover {
  transform: scale(1.05);
}
.contract-preview {
  white-space: pre-wrap;
  font-family: 'Arial', sans-serif;
  font-size: 14px;
  line-height: 1.6;
}
</style>