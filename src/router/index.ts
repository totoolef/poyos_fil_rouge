import { createRouter, createWebHistory } from 'vue-router';
import DashboardAnnonceur from '../components/Navigation/NavigationAnnonceur.vue';
import AnnoncesPostees from '../pages/annonceur/annonces-postees.vue';
import CreerAnnonce from '../pages/annonceur/creer-annonce.vue';
import ParametresAnnonceur from '../pages/annonceur/parametres.vue';
import Candidatures from '../pages/annonceur/candidatures.vue';
import MesContrats from '../pages/annonceur/mes-contrats.vue';

import DashboardConducteur from '../components/Navigation/NavigationConducteur.vue';
import AnnoncesDisponibles from '../pages/conducteur/annonces-disponibles.vue';
import ParametresConducteur from "../pages/conducteur/parametres.vue";
import MesCandidatures from '../pages/conducteur/mes-candidatures.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'connexion', component: () => import('../pages/connexion.vue') },
    { path: '/inscription', name: 'inscription', component: () => import('../pages/inscription.vue') },

    {
      path: '/dashboard-annonceur',
      component: DashboardAnnonceur,
      meta: { requiresAuth: true, role: 'commercant' },
      children: [
        { path: 'annonces-postees', name: 'annonces-postees', component: AnnoncesPostees },
        { path: 'creer-annonce', name: 'creer-annonce', component: CreerAnnonce },
        { path: 'candidatures', name: 'candidatures', component: Candidatures },
        { path: 'contrats', name: 'contrats', component: MesContrats },
        // { path: 'paiements', name: 'paiements', component: () => import('../views/dashboard/Paiements.vue') },
        { path: 'parametres-annonceur', name: 'parametres-annonceur', component: ParametresAnnonceur },
      ],
    },
    {
      path: '/dashboard-conducteur',
      component: DashboardConducteur,
      meta: { requiresAuth: true, role: 'conducteur' },
      children: [
        { path: 'annonces-disponibles', name: 'annonces-disponibles', component: AnnoncesDisponibles },
        { path: 'mes-candidatures', name: 'mes-candidatures', component: MesCandidatures },
        // { path: 'mes-contrats', name: 'mes-contrats', component: MesContrats },
        // { path: 'mes-paiements', name: 'mes-paiements', component: MesPaiements },
        { path: 'parametres-conducteur', name: 'parametres-conducteur', component: ParametresConducteur },
      ],
    },
  ],
});

// // Guard global pour auth et rôle (simulation)
// router.beforeEach((to, _from, next) => {
//   const estAuthentifie = true; // À remplacer par ta logique (ex. token localStorage)
//   const role = 'commercant'; // À remplacer par le rôle stocké après connexion

//   if (to.meta.requiresAuth && !estAuthentifie) {
//     next('/connexion');
//   } else if (to.meta.role && to.meta.role !== role) {
//     next('/dashboard'); // Ou une page d'erreur
//   } else {
//     next();
//   }
// });

export default router;