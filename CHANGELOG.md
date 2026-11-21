# Changelog

Toutes les évolutions notables de ce projet sont consignées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), et ce projet adhère au
[Semantic Versioning](https://semver.org/lang/fr/). Les entrées à publier sont dans
`changes/unreleased/` (une par PR), rangées ici à la publication.

## [Unreleased]

## [0.1.0] - 2026-11-21

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

- **Source Sangfor HCI par Sangfor SCP** (issue #11) : lecture seule de l'API OpenAPI de Sangfor
  Cloud Platform 6.3.0 ou plus, sur clic depuis Gestion > FinOps > Import (« Lire maintenant »).
  Nœuds (`/janus/20180725/hosts`) et machines virtuelles avec leurs allocations : vCPU, mémoire,
  disque, état, nœud (`/janus/20180725/servers`, toutes les pages). Format tolérant : plusieurs noms
  de champs acceptés pour chaque valeur, VM sans identifiant écartées et comptées.
- **Grille de prix par plateforme** (prix mensuel d'un vCPU, d'un Go de mémoire, d'un Go de disque,
  devise) : Sangfor ne facture pas à l'usage, le plugin calcule une ligne de coût par VM et par mois
  (vCPU et mémoire comptés si la VM n'est pas éteinte, disque toujours). La première lecture du mois
  fixe le coût du mois ; relire ne crée aucun doublon. Grille à 0 : inventaire sans coût.
- **Page d'inventaire** d'une plateforme : nœuds, VM, totaux alloués, coût du mois et rattachement
  de chaque VM à un ordinateur de GLPI, proposé par UUID puis par nom et validé par un clic (rien
  n'est rattaché tout seul).
- Plateformes gérées dans la configuration du plugin, onglet « Sources de coûts » : nom, entité,
  adresse, identifiant, mot de passe, certificat auto-signé, grille de prix.

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

- Identifiant et mot de passe chiffrés par GLPI, jamais réaffichés ; mot de passe envoyé chiffré
  en RSA avec la clé publique de SCP, puis jeton. Seule l'adresse configurée est appelée (https
  obligatoire, aucune redirection suivie, aucune adresse reprise d'une réponse) ; le seul POST est
  l'authentification.
- Certificat de SCP vérifié par défaut ; un certificat auto-signé n'est accepté que si
  l'administrateur coche l'option de la plateforme, signalée par un badge.
- Une lecture en échec n'écrit rien (ni inventaire ni coût) et garde la lecture précédente ; le
  message affiché ne reprend ni les secrets ni le texte renvoyé par SCP.

- Lecture sûre des fichiers de coûts : taille bornée par un réglage avant toute lecture, extension
  et type réel vérifiés, fichier binaire ou contenant du code refusé, nombre de lignes et de colonnes
  borné, cellules nettoyées et tronquées, dates et montants validés ; fichier en attente stocké sous
  un nom aléatoire et rattaché à la session. Tests avec des fichiers piégés, matrice d'accès et test
  XSS de bout en bout (`tests/security/*.d/2-cost-import.*`).

- Clés OVHcloud (application key, application secret, consumer key) chiffrées par GLPI, jamais
  réaffichées (un champ laissé vide garde la clé) ; requêtes GET signées, sans redirection, proxy
  de GLPI, nombre d'appels borné. Gérer les comptes exige le droit de configuration de GLPI ; lire,
  le droit d'import du plugin et l'accès à l'entité du compte.
- Une lecture en échec n'écrit aucune ligne et garde la lecture précédente ; le message affiché ne
  reprend ni les clés ni le texte renvoyé par OVHcloud.

