import { defineStore } from 'pinia';
import { ref, onMounted } from 'vue';
import axios from 'axios';

export const useUserStore = defineStore('user', () => {
  const isAuthenticated = ref(false);
  const user = ref({
    id: null,
    email: '',
    nom: '',
    prenom: '',
    nomEntreprise: '',
    telephone: '',
    adresse: '',
    role: '',
  });

  const login = (data: { role: string; token: string; id: string; }) => {
    isAuthenticated.value = true;
    user.value.role = data.role;
    // Stocke le token et l'ID
    localStorage.setItem('token', data.token);
    localStorage.setItem('userId', data.id);
    chargerInfosUtilisateur(); // Charge les infos après login
  };

  const logout = () => {
    isAuthenticated.value = false;
    user.value = { id: null, email: '', nom: '', prenom: '', nomEntreprise: '', telephone: '', adresse: '', role: '' };
    localStorage.removeItem('token');
    localStorage.removeItem('userId');
  };

  const chargerInfosUtilisateur = async () => {
    const token = localStorage.getItem('token');
    if (!token) return;

    try {
      const reponse = await axios.get('http://localhost:8000/get_utilisateur_infos.php', {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (reponse.data.success) {
        user.value = reponse.data.user;
      } else {
        logout(); // Déconnecte si erreur
      }
    } catch (erreur) {
      console.error('Erreur chargement infos utilisateur:', erreur);
      logout(); // Déconnecte si erreur
    }
  };

  // Charge au démarrage si token existe
  onMounted(() => {
    if (localStorage.getItem('token')) {
      chargerInfosUtilisateur();
    }
  });

  return { isAuthenticated, user, login, logout, chargerInfosUtilisateur };
});