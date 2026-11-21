### Added

- **Import CSV générique des coûts** (issue #2) : Gestion > FinOps > Import. Fichier au format commun
  (date, fournisseur, compte ou abonnement, service, ressource, coût, devise, étiquettes ; synonymes
  de colonnes acceptés, séparateur virgule, point-virgule ou tabulation), dans une entité choisie.
  Aperçu avant écriture (lignes nouvelles, déjà importées, rejetées avec leur numéro et leur raison),
  import rejouable sans doublon, journal des imports et suppression d'un import avec ses lignes.
- **Coûts par fournisseur et par mois** sur la page d'accueil, une table par devise (jamais
  d'addition entre devises), avec les chiffres clés (lignes, ressources, ressources non rattachées,
  dernier import).
- **Ressources et rattachement manuel** : Gestion > FinOps > Ressources liste chaque ressource
  facturée avec son coût ; elle se rattache à un ordinateur, une application ou un matériel réseau
  de son entité, qui reçoit un onglet « FinOps » (ressources et coût par mois).
- Droits du plugin : lire, importer, rattacher, supprimer un import.

### Security

- Lecture sûre des fichiers de coûts : taille bornée par un réglage avant toute lecture, extension
  et type réel vérifiés, fichier binaire ou contenant du code refusé, nombre de lignes et de colonnes
  borné, cellules nettoyées et tronquées, dates et montants validés ; fichier en attente stocké sous
  un nom aléatoire et rattaché à la session. Tests avec des fichiers piégés, matrice d'accès et test
  XSS de bout en bout (`tests/security/*.d/2-cost-import.*`).
