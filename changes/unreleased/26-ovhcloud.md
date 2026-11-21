### Added

- **Source OVHcloud** (issue #26) : lecture seule de l'API OVHcloud, région Europe ou Canada, sur
  clic depuis Gestion > FinOps > Import (« Lire maintenant »). Factures (`/me/bill` et leurs
  détails) et consommation par ressource des projets Public Cloud listés
  (`/cloud/project/{id}/usage/history`, périodes closes) ; les lignes de facture d'un projet listé
  sont écartées pour ne pas compter deux fois. Les coûts vont dans les mêmes tables que l'import CSV
  (même journal, mêmes ressources, aucun doublon d'une lecture à l'autre).
- Comptes OVHcloud gérés dans la configuration du plugin, onglet « Sources de coûts » : nom, entité,
  région, projets, nombre de mois lus ; état de la dernière lecture (réussie, en échec avec son
  motif, date de la dernière réussite).

### Security

- Clés OVHcloud (application key, application secret, consumer key) chiffrées par GLPI, jamais
  réaffichées (un champ laissé vide garde la clé) ; requêtes GET signées, sans redirection, proxy
  de GLPI, nombre d'appels borné. Gérer les comptes exige le droit de configuration de GLPI ; lire,
  le droit d'import du plugin et l'accès à l'entité du compte.
- Une lecture en échec n'écrit aucune ligne et garde la lecture précédente ; le message affiché ne
  reprend ni les clés ni le texte renvoyé par OVHcloud.
