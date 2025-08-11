<template>
  <v-card>
    <v-card-title class="headline pa-4 primary white--text">
      <h2 class="mb-0">{{ annonce.titre }}</h2>
    </v-card-title>
    <v-card-text class="pa-4">
      <v-row>
        <v-col cols="12">
          <v-chip :color="getStatutColor(annonce.statut)" label size="small" class="mb-2">
            {{ annonce.statut }}
          </v-chip>
        </v-col>
        <v-col cols="12" md="6">
          <v-icon small class="mr-2">mdi-car</v-icon>
          <strong>Type de pub :</strong> {{ annonce.type_pub }}
        </v-col>
        <v-col cols="12" md="6">
          <v-icon small class="mr-2">mdi-map-marker</v-icon>
          <strong>Localisation :</strong> {{ annonce.localisation }}
        </v-col>
        <v-col cols="12" md="6">
          <v-icon small class="mr-2">mdi-truck</v-icon>
          <strong>Véhicules recherchés :</strong> {{ annonce.nombre_vehicules }}
        </v-col>
        <v-col cols="12" md="6">
          <v-icon small class="mr-2">mdi-calendar</v-icon>
          <strong>Durée :</strong> {{ annonce.duree_mois }} mois
        </v-col>
        <v-col cols="12" md="6">
          <v-icon small class="mr-2">mdi-currency-eur</v-icon>
          <strong>Paiement/mois :</strong> {{ annonce.paiement_mensuel }} €
        </v-col>
        <v-col cols="12">
          <v-icon small class="mr-2">mdi-text</v-icon>
          <strong>Description :</strong>
          <p class="mt-2">{{ annonce.description }}</p>
        </v-col>
      </v-row>
    </v-card-text>
    <v-card-actions class="pa-4">
      <v-btn color="primary" variant="outlined" @click="$emit('candidater')">
        Candidater
      </v-btn>
      <v-spacer></v-spacer>
      <v-btn color="red-lighten-1" @click="$emit('close')">Fermer</v-btn>
    </v-card-actions>
  </v-card>
</template>

<script setup lang="ts">
defineProps<{
  annonce: {
    id: number;
    type_pub: string;
    titre: string;
    description: string;
    localisation: string;
    nombre_vehicules: number;
    duree_mois: number;
    paiement_mensuel: number;
    statut: string;
  };
}>();

const getStatutColor = (statut: string) => {
  switch (statut) {
    case 'ouverte': return 'success';
    case 'en_cours': return 'warning';
    case 'fermee': return 'error';
    default: return 'grey';
  }
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
