<template>
  <v-row no-gutters>
    <v-col cols="12" sm="6" class="d-flex flex-column">
      <v-img
        :src="image1"
        cover
        max-height="650"
        class="ma-0"
      ></v-img>
      <div class="pa-4 grey lighten-4 text-center flex-grow-1 my-4">
        <h2 class="text-h4 font-weight-bold mb-3">S'inscrire en conducteur</h2>
        <p class="body-2 mb-4">Rechercher des annonces, postuler et obtenir une rémunération</p>
        <v-btn color="primary" @click="particulierInscription = true" depressed>Inscription</v-btn>
      </div>
    </v-col>
    <v-col cols="12" sm="6" class="d-flex flex-column">
      <v-img
        :src="image2"
        cover
        max-height="650"
        class="ma-0"
      ></v-img>
      <div class="pa-4 grey lighten-4 text-center flex-grow-1 my-4">
        <h2 class="text-h4 font-weight-bold mb-3">S'inscrire en annonceur</h2>
        <p class="body-2 mb-4">Obtenir et élargir votre visibilité, local ou non</p>
        <v-btn color="green-darken-1" @click="annonceurInscription = true" depressed>Inscription</v-btn>
      </div>
    </v-col>
  </v-row>

  <v-dialog v-model="particulierInscription" width="700px">
    <InscriptionParticulier @fermerDialog="fermerInscriptionParticulier()" />
  </v-dialog>

  <v-dialog v-model="annonceurInscription" width="700px">
    <InscriptionAnnonceur @fermerDialog="fermerInscriptionAnnonceur()"/>
  </v-dialog>
</template>

<script setup lang="ts">
//import {ref} from 'vue'
import image1 from '../assets/inscription1.jpg';
import image2 from '../assets/inscription2.jpg';
import InscriptionParticulier from '../dialog/InscriptionParticulier.vue';
import InscriptionAnnonceur from '../dialog/InscriptionAnnonceur.vue';
import { ref } from 'vue';

const particulierInscription = ref<boolean>(false)
const annonceurInscription = ref<boolean>(false)

function fermerInscriptionParticulier(){
  particulierInscription.value = false
}

function fermerInscriptionAnnonceur(){
  annonceurInscription.value = false
}

</script>

<style scoped>
/* Ajout de styles pour assurer une hauteur cohérente */
.v-col {
  display: flex;
  flex-direction: column;
}

/* Assure que le contenu textuel prend l'espace restant */
.flex-grow-1 {
  flex-grow: 1;
}

/* Media query pour ajuster la hauteur des images sur petits écrans */
@media (max-width: 600px) {
  .v-img {
    max-height: 300px;
  }
}
</style>
