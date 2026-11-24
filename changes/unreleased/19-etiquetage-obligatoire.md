### Added

- **Étiquetage obligatoire et ressources sans propriétaire** (issue #19) : réglage des étiquettes
  attendues (par défaut `owner`, `cost-center`, `env`) et de l'étiquette du propriétaire dans
  Configuration > Plugins > FinOps, onglet Général. La page Gestion > FinOps > Ressources affiche le
  taux de conformité global, par fournisseur et par compte, les étiquettes manquantes de chaque
  ressource et un filtre « Étiquetage à corriger seulement ».
- Le propriétaire est rapproché d'un utilisateur de GLPI par son identifiant de connexion ou son
  adresse de courriel (casse ignorée) : vert s'il est retrouvé, orange s'il est inconnu, rouge si
  l'étiquette manque. Rien n'est modifié chez le fournisseur ni dans GLPI.
- Chaque ressource garde les étiquettes de sa ligne de coût la plus récente ; la mise à jour les
  reprend une fois des coûts déjà importés.
