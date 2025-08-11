<template>
  <v-app>
    <v-app-bar color="indigo-lighten-2" app flat>
      <!-- Your logo and menu code here (from previous App.vue) -->
      <router-link to="/" class="text-decoration-none ml-4">
        <v-toolbar-title style="color: white; cursor: pointer;">Poyos</v-toolbar-title>
      </router-link>
      <!-- Add your hamburger and menu items as before -->
    </v-app-bar>

    <v-main>
      <router-view />
    </v-main>

    <v-footer app color="indigo-lighten-1" class="text-center py-4">
      <!-- Your footer code here -->
    </v-footer>
  </v-app>
</template>

<script setup lang="ts">
// Your script code here (tiroir, elementsMenu, etc.)
</script>