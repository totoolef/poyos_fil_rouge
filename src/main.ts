import { createApp } from 'vue';
import App from './App.vue';
import { createVuetify } from 'vuetify';
import 'vuetify/styles';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';
import '@mdi/font/css/materialdesignicons.css'; // Icônes MDI
import router from './router'; // Importe le router
import { createPinia } from 'pinia';

const vuetify = createVuetify({
  components,
  directives,
  theme: { defaultTheme: 'light' }, // Theme responsive
});

const app = createApp(App);
app.use(createPinia());
app.use(vuetify);
app.use(router);
app.mount('#app');