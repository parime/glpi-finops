### Added

- **Socle du plugin FinOps** (issue #1), créé depuis le modèle commun : clé `finops`, espace de noms
  `GlpiPlugin\Finops`, GLPI 11 et 12 (couche `src/Compat/`), une seule entrée de menu (Gestion >
  FinOps) avec sa page d'accueil, page de configuration en onglets (Général : devise par défaut et
  taille maximale d'un fichier ; Sources de coûts ; À propos), droit du plugin attribuable depuis
  l'onglet FinOps des profils, 18 langues.
- Sources de coûts extensibles : une source est une classe `src/Source/<Nom>Source.php`, découverte
  sans liste centrale ; tables découvertes par l'installeur (`src/Install/*Tables.php`).
- CI : CI officielle GLPI, installation, PHPUnit et PHPStan sur GLPI 12, chargement des classes sur
  PHP 8.2 à 8.5, matrice d'accès et test XSS de bout en bout en fragments (`tests/security/*.d/`),
  traductions complètes, CHANGELOG et tutoriel en fragments, contrôles communs glpi-plugin-security
  v1.9.1.
- Fiche du catalogue de la marketplace (`finops.xml`), logo et ébauche d'annonce pour le forum GLPI
  (`docs/forum-announcement.md`).
