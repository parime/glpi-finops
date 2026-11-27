### Fixed

- **Ajout d'une plateforme Sangfor SCP derrière un pare-feu applicatif** (issue #48) : le formulaire
  envoyait l'adresse complète (`https://10.0.0.1:4430`) dans un champ `url`, motif qu'un pare-feu
  applicatif (par exemple FortiWeb, signature « RFI Injection ») peut prendre pour une inclusion de
  fichier distant et bloquer. Le nom (ou l'adresse IP) et le port se saisissent désormais dans deux
  champs séparés, `https://` est affiché devant et imposé par le serveur : aucune URL ne part dans la
  requête. Les plateformes déjà enregistrées ne changent pas ; les contrôles de l'adresse restent les
  mêmes (https seulement, sans chemin, paramètre ni identifiant).
