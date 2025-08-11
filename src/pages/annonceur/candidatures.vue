<template>
  <v-container fluid class="pa-4">
    <v-card class="elevation-3 rounded-lg pa-4">
      <h2 class="text-h6 mb-4">Candidatures reçues</h2>
      <v-tabs v-model="tabStatut" color="primary" align-tabs="center" class="mb-4">
        <v-tab value="toutes">Toutes</v-tab>
        <v-tab value="en_attente">En attente</v-tab>
        <v-tab value="acceptee">Acceptées</v-tab>
        <v-tab value="refusee">Refusées</v-tab>
        <v-tab value="annulee">Annulées</v-tab>
      </v-tabs>
      <v-progress-linear v-if="enChargement" indeterminate color="primary" class="mb-4"></v-progress-linear>
      <v-expansion-panels v-model="panel" multiple>
        <v-expansion-panel v-for="(groupe, annonceId) in candidaturesGroupes" :key="annonceId">
          <v-expansion-panel-title>
            <v-icon small class="mr-2">mdi-bullhorn</v-icon>
            {{ groupe.titre }} ({{ groupe.candidatures.length }} candidatures)
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-row>
              <v-col v-for="(candidature, index) in groupe.candidatures" :key="index" cols="12" md="6">
                <v-card class="elevation-2 pa-4 animated-card">
                  <v-card-title class="text-subtitle-1">
                    Candidature de {{ candidature.nom_conducteur }} {{ candidature.prenom_conducteur }}
                  </v-card-title>
                  <v-card-subtitle>{{ candidature.created_at }}</v-card-subtitle>
                  <v-card-text>
                    <p><strong>Message :</strong> {{ candidature.message }}</p>
                    <p><strong>Voiture :</strong> {{ candidature.marque_voiture }} {{ candidature.modele_voiture }} ({{ candidature.couleur }})</p>
                    <v-chip :color="getStatutColor(candidature.statut)" size="small" class="mt-2">
                      {{ candidature.statut }}
                    </v-chip>
                  </v-card-text>
                  <v-card-actions v-if="candidature.statut === 'en_attente'">
                    <v-btn color="success" variant="outlined" size="small" @click="accepterCandidature(candidature)">
                      Accepter
                    </v-btn>
                    <v-btn color="error" variant="outlined" size="small" @click="refuserCandidature(candidature)">
                      Refuser
                    </v-btn>
                  </v-card-actions>
                  <v-card-actions v-if="candidature.statut === 'acceptee'">
                    <v-btn color="primary" variant="outlined" size="small" @click="ouvrirDialogContrat(candidature)">
                      Générer contrat
                    </v-btn>
                  </v-card-actions>
                </v-card>
              </v-col>
            </v-row>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <v-dialog v-model="dialogContrat" max-width="800">
        <v-card>
          <v-card-title class="headline pa-4 primary white--text">
            <h2 class="mb-0">Prévisualisation du contrat</h2>
          </v-card-title>
          <v-card-text class="pa-4">
            <v-alert v-if="!contrat" type="info" class="mb-4">Chargement du contrat...</v-alert>
            <div v-else v-html="contrat" class="contract-preview"></div>
          </v-card-text>
          <v-card-actions class="pa-4">
            <v-btn color="primary" @click="telechargerContrat">Télécharger PDF</v-btn>
            <v-spacer></v-spacer>
            <v-btn color="red-lighten-1" @click="dialogContrat = false">Fermer</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
      <v-alert v-if="messageErreur" type="error" class="mt-4">{{ messageErreur }}</v-alert>
      <v-alert v-if="!Object.keys(candidaturesGroupes).length && !enChargement" type="info" class="mt-4">Aucune candidature reçue pour le moment.</v-alert>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import jsPDF from 'jspdf';

// Types pour les données
interface Candidature {
  id: number;
  annonce_id: number;
  conducteur_id: number;
  message: string;
  marque_voiture: string | null;
  modele_voiture: string | null;
  couleur: string | null;
  statut: string;
  created_at: string;
  updated_at: string;
  nom_conducteur: string | null;
  prenom_conducteur: string | null;
  titre_annonce: string | null;
  type_pub: string | null;
  localisation: string | null;
  localisation_type: string | null;
  localisation_personnalisee: string | null;
  code_postal: string | null;
  ville: string | null;
  duree_mois: number | null;
  paiement_mensuel: number | null;
  annonceur_id: number | null; // Ajouté pour récupérer l'ID de l'annonceur
}

const candidatures = ref<Candidature[]>([]);
const tabStatut = ref('toutes');
const panel = ref<number[]>([]);
const enChargement = ref(true);
const messageErreur = ref<string | null>(null);
const dialogContrat = ref(false);
const candidatureSelectionnee = ref<Candidature | null>(null);
const contrat = ref<string | null>(null);

const candidaturesFiltrees = computed(() => {
  if (tabStatut.value === 'toutes') {
    return candidatures.value;
  }
  return candidatures.value.filter(candidature => candidature.statut === tabStatut.value);
});

const candidaturesGroupes = computed(() => {
  const groupes: { [annonceId: number]: { titre: string; candidatures: Candidature[] } } = {};
  candidaturesFiltrees.value.forEach(candidature => {
    const annonceId = candidature.annonce_id;
    const titre = candidature.titre_annonce || 'Annonce inconnue';
    if (!groupes[annonceId]) {
      groupes[annonceId] = { titre, candidatures: [] };
    }
    groupes[annonceId].candidatures.push(candidature);
  });
  return groupes;
});

const getStatutColor = (statut: string) => {
  switch (statut) {
    case 'en_attente': return 'warning';
    case 'acceptee': return 'success';
    case 'refusee': return 'error';
    case 'annulee': return 'grey';
    default: return 'grey';
  }
};

const chargerCandidatures = async () => {
  const token = localStorage.getItem('token');
  if (!token) {
    messageErreur.value = 'Veuillez vous connecter';
    enChargement.value = false;
    return;
  }

  try {
    const reponse = await axios.get('http://localhost:8000/get_candidatures_annonceur.php', {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (reponse.data.success) {
      candidatures.value = reponse.data.candidatures as Candidature[];
    } else {
      messageErreur.value = reponse.data.message || 'Échec du chargement des candidatures';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  } finally {
    enChargement.value = false;
  }
};

onMounted(chargerCandidatures);

const ouvrirDialogContrat = (candidature: Candidature) => {
  candidatureSelectionnee.value = candidature;
  dialogContrat.value = true;
  genererContratPreview();
};

const genererContratPreview = async () => {
  if (!candidatureSelectionnee.value) {
    messageErreur.value = 'Aucune candidature sélectionnée';
    return;
  }
  const token = localStorage.getItem('token');
  try {
    const reponse = await axios.post('http://localhost:8000/generer_contrat.php', {
      candidature_id: candidatureSelectionnee.value.id,
    }, {
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    });
    if (reponse.data.success) {
      contrat.value = reponse.data.contrat_html;
    } else {
      messageErreur.value = reponse.data.message || 'Échec de la génération du contrat';
    }
  } catch (erreur) {
    console.error('Erreur Axios:', erreur);
    messageErreur.value = 'Erreur de connexion au serveur';
  }
};

const telechargerContrat = () => {
  if (!contrat.value || !candidatureSelectionnee.value) {
    messageErreur.value = 'Aucun contrat à télécharger';
    return;
  }
  const doc = new jsPDF();
  doc.setFontSize(11);
  doc.setFont("helvetica", "normal");
  doc.text('Contrat de Publicité Véhicule - Poyos', 20, 20);
  doc.text(`Date : ${new Date().toLocaleDateString()}`, 20, 30);
  doc.text(`Annonceur : [Ton nom] (ID: ${candidatureSelectionnee.value.annonceur_id || 'Inconnu'})`, 20, 40);
  doc.text(`Conducteur : ${candidatureSelectionnee.value.nom_conducteur || 'Inconnu'} ${candidatureSelectionnee.value.prenom_conducteur || 'Inconnu'}`, 20, 50);
  doc.text(`Annonce : ${candidatureSelectionnee.value.titre_annonce || 'Inconnue'} (Type : ${candidatureSelectionnee.value.type_pub || 'Non spécifié'})`, 20, 60);
  doc.text(`Localisation : ${candidatureSelectionnee.value.localisation || 'Non spécifiée'}`, 20, 70);
  doc.text(`Durée : ${candidatureSelectionnee.value.duree_mois || 0} mois`, 20, 80);
  doc.text(`Paiement mensuel : ${candidatureSelectionnee.value.paiement_mensuel || 0} €`, 20, 90);
  doc.text(`Commission Poyos (12%) : ${((candidatureSelectionnee.value.paiement_mensuel || 0) * 0.12).toFixed(2)} €`, 20, 100);
  doc.text('Clauses : L\'annonceur s\'engage à fournir les visuels et organiser la pose (estimé 1500-3000€). Le conducteur s\'engage à soumettre des validations mensuelles.', 20, 110, { maxWidth: 160 });
  doc.text(`Voiture : ${candidatureSelectionnee.value.marque_voiture || 'Non spécifié'} ${candidatureSelectionnee.value.modele_voiture || 'Non spécifié'} (${candidatureSelectionnee.value.couleur || 'Non spécifié'})`, 20, 120, { maxWidth: 160 });
  doc.save(`contrat_${candidatureSelectionnee.value.id}.pdf`);
};

const accepterCandidature = async (candidature: Candidature) => {
  if (confirm('Voulez-vous accepter cette candidature ?')) {
    try {
      const token = localStorage.getItem('token');
      const reponse = await axios.post('http://localhost:8000/modifier_statut_candidature.php', {
        candidature_id: candidature.id,
        statut: 'acceptee',
      }, {
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      });
      if (reponse.data.success) {
        alert('Candidature acceptée avec succès');
        chargerCandidatures();
      } else {
        alert(reponse.data.message || 'Échec de l\'acceptation');
      }
    } catch (erreur) {
      console.error('Erreur Axios:', erreur);
      alert('Erreur de connexion au serveur');
    }
  }
};

const refuserCandidature = async (candidature: Candidature) => {
  if (confirm('Voulez-vous refuser cette candidature ?')) {
    try {
      const token = localStorage.getItem('token');
      const reponse = await axios.post('http://localhost:8000/modifier_statut_candidature.php', {
        candidature_id: candidature.id,
        statut: 'refusee',
      }, {
        headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
      });
      if (reponse.data.success) {
        alert('Candidature refusée avec succès');
        chargerCandidatures();
      } else {
        alert(reponse.data.message || 'Échec du refus');
      }
    } catch (erreur) {
      console.error('Erreur Axios:', erreur);
      alert('Erreur de connexion au serveur');
    }
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