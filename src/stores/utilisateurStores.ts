import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useUserStore = defineStore('user', () => {
  const isAuthenticated = ref(false);
  const user = ref({
    id: null as string | null,
    email: '',
    nom: '',
    prenom: '',
    nomEntreprise: '',
    telephone: '',
    adresse: '',
    role: '',
  });

  // Initialisation synchrone au démarrage
  const initializeStore = () => {
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');
    const userId = localStorage.getItem('userId');
    
    console.log('Store: Initialisation - Token:', token ? 'présent' : 'absent');
    console.log('Store: Initialisation - Rôle:', role);
    console.log('Store: Initialisation - UserId:', userId);
    
    if (token && role) {
      user.value.role = role;
      user.value.id = userId;
      isAuthenticated.value = true;
      console.log('Store: État initial - isAuthenticated:', isAuthenticated.value, 'role:', user.value.role);
    }
  };

  const login = (data: { role: string; token: string; id: string; }) => {
    console.log('Store: login appelé avec:', data);
    isAuthenticated.value = true;
    user.value.role = data.role;
    user.value.id = data.id;
    // Stocke le token et l'ID
    localStorage.setItem('token', data.token);
    localStorage.setItem('userId', data.id);
    localStorage.setItem('role', data.role);
    console.log('Store: État après login - isAuthenticated:', isAuthenticated.value, 'role:', user.value.role);
    chargerInfosUtilisateur(); // Charge les infos après login
  };

  const logout = () => {
    isAuthenticated.value = false;
    user.value = { id: null as string | null, email: '', nom: '', prenom: '', nomEntreprise: '', telephone: '', adresse: '', role: '' };
    localStorage.removeItem('token');
    localStorage.removeItem('userId');
    localStorage.removeItem('role');
  };

  const chargerInfosUtilisateur = async () => {
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');
    if (!token || !role) return;

    try {
      // Utilise l'endpoint approprié selon le rôle
      const endpoint = role === 'conducteur' 
        ? 'http://localhost:8080/utilisateurs/get_utilisateur_infos_conducteur.php'
        : 'http://localhost:8080/utilisateurs/get_utilisateur_infos.php';
      
      const reponse = await axios.get(endpoint, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (reponse.data.success) {
        user.value = { ...user.value, ...reponse.data.user };
        isAuthenticated.value = true;
      } else {
        logout(); // Déconnecte si erreur
      }
    } catch (erreur) {
      console.error('Erreur chargement infos utilisateur:', erreur);
      logout(); // Déconnecte si erreur
    }
  };

  // Initialiser le store immédiatement
  initializeStore();

  return { isAuthenticated, user, login, logout, chargerInfosUtilisateur };
});