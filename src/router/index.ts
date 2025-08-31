import { createRouter, createWebHistory } from 'vue-router';
import { useUserStore } from '../stores/utilisateurStores';
import DashboardAnnonceur from '../components/Navigation/NavigationAnnonceur.vue';
import AnnoncesPostees from '../pages/annonceur/annonces-postees.vue';
import CreerAnnonce from '../pages/annonceur/creer-annonce.vue';
import ParametresAnnonceur from '../pages/annonceur/parametres.vue';
import Candidatures from '../pages/annonceur/candidatures.vue';
import CandidaturesAcceptees from '../pages/annonceur/candidatures-acceptees.vue';
import DeposerBrief from '../pages/annonceur/deposer-brief.vue';
import ListeBriefAnnonceur from '../pages/annonceur/liste-brief.vue';
import ContratsPaiements from '../pages/annonceur/contrats-paiements.vue';
import SuiviCampagneAnnonceur from '../pages/annonceur/suivi-campagne.vue';
import PaiementsAnnonceur from '../pages/annonceur/paiements.vue';

import DashboardConducteur from '../components/Navigation/NavigationConducteur.vue';
import AnnoncesDisponibles from '../pages/conducteur/annonces-disponibles.vue';
import ParametresConducteur from "../pages/conducteur/parametres.vue";
import MesCandidatures from '../pages/conducteur/mes-candidatures.vue';
import ListeDesignConducteur from '../pages/conducteur/liste-brief.vue';
import Contrats from '../pages/conducteur/contrats.vue';
import SuiviCampagneConducteur from '../pages/conducteur/suivi-campagne.vue';
import PaiementsConducteur from '../pages/conducteur/paiements.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'connexion', component: () => import('../pages/connexion.vue') },
    { path: '/inscription', name: 'inscription', component: () => import('../pages/inscription.vue') },


    {
      path: '/dashboard-annonceur',
      component: DashboardAnnonceur,
      meta: { requiresAuth: true, role: 'annonceur' },
      children: [
        { path: '', redirect: { name: 'annonces-postees' } },
        { path: 'annonces-postees', name: 'annonces-postees', component: AnnoncesPostees },
        { path: 'creer-annonce', name: 'creer-annonce', component: CreerAnnonce },
        { path: 'candidatures', name: 'candidatures', component: Candidatures },
        { path: 'candidatures-acceptees', name: 'candidatures-acceptees', component: CandidaturesAcceptees },
        // { path: 'paiements', name: 'paiements', component: () => import('../views/dashboard/Paiements.vue') },
        { path: 'parametres-annonceur', name: 'parametres-annonceur', component: ParametresAnnonceur },
        { path: 'deposer-brief', name: 'deposer-brief', component: DeposerBrief },
        { path: 'liste-brief', name: 'liste-brief', component: ListeBriefAnnonceur },
        { path: 'contrats-paiements', name: 'contrats-paiements', component: ContratsPaiements },
        { path: 'paiements', name: 'paiements-annonceur', component: PaiementsAnnonceur },
        { path: 'suivi-campagnes', name: 'suivi-campagnes', component: SuiviCampagneAnnonceur },
      ],
    },
    {
      path: '/dashboard-conducteur',
      component: DashboardConducteur,
      meta: { requiresAuth: true, role: 'conducteur' },
      children: [
        { path: '', redirect: { name: 'campagnes' } },
        { path: 'annonces-disponibles', name: 'annonces-disponibles', component: AnnoncesDisponibles },
        { path: 'mes-candidatures', name: 'mes-candidatures', component: MesCandidatures },
        { path: 'contrats', name: 'contrats', component: Contrats },
        { path: 'paiements', name: 'paiements-conducteur', component: PaiementsConducteur },
        { path: 'liste-design', name: 'liste-design', component: ListeDesignConducteur },
        { path: 'campagnes', name: 'campagnes', component: SuiviCampagneConducteur },
        { path: 'parametres-conducteur', name: 'parametres-conducteur', component: ParametresConducteur },
        
      ],
    },
  ],
});

// Guard global pour auth et rôle
router.beforeEach((to, _from, next) => {
  const userStore = useUserStore();
  
  // Vérifier directement dans le localStorage pour éviter les problèmes d'initialisation
  const token = localStorage.getItem('token');
  const role = localStorage.getItem('role');
  const estAuthentifie = userStore.isAuthenticated || (token && role);
  
  console.log('Router guard - Route:', to.path);
  console.log('Router guard - Token:', token ? 'présent' : 'absent');
  console.log('Router guard - Role:', role);
  console.log('Router guard - isAuthenticated (store):', userStore.isAuthenticated);
  console.log('Router guard - estAuthentifie (calculé):', estAuthentifie);
  console.log('Router guard - meta:', to.meta);

  // Si on va vers la page de connexion ou d'inscription, autoriser
  if (to.path === '/' || to.path === '/connexion' || to.path === '/inscription') {
    console.log('Router guard - Navigation vers page publique autorisée');
    next();
    return;
  }

  // Si authentification requise mais pas authentifié
  if (to.meta.requiresAuth && !estAuthentifie) {
    console.log('Router guard - Redirection vers connexion (non authentifié)');
    next('/');
    return;
  }

  // Si authentifié mais rôle ne correspond pas
  if (to.meta.role && estAuthentifie && to.meta.role !== role) {
    console.log('Router guard - Rôle ne correspond pas, redirection...');
    // Rediriger vers le bon dashboard selon le rôle
    if (role === 'conducteur') {
      console.log('Router guard - Redirection vers dashboard conducteur');
      next('/dashboard-conducteur/campagnes');
    } else if (role === 'annonceur') {
      console.log('Router guard - Redirection vers dashboard annonceur');
      next('/dashboard-annonceur');
    } else {
      console.log('Router guard - Rôle inconnu, redirection vers connexion');
      next('/');
    }
    return;
  }

  // Si tout est OK, autoriser la navigation
  console.log('Router guard - Navigation autorisée');
  next();
});

export default router;