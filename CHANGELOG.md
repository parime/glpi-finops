# Changelog

Toutes les évolutions notables de ce projet sont consignées ici.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), et ce projet adhère au
[Semantic Versioning](https://semver.org/lang/fr/). Les entrées à publier sont dans
`changes/unreleased/` (une par PR), rangées ici à la publication.

## [Unreleased]

## [0.3.0] - 2026-11-27

### Added

- **Rattachement assisté des ressources aux actifs de GLPI** (issue #6) : pour chaque ressource pas
  encore rattachée, la page Gestion > FinOps > Ressources propose un ordinateur, une application ou
  un matériel réseau, avec la règle qui l'a trouvé. Règles, dans l'ordre réglé dans Configuration >
  Plugins > FinOps, onglet Général (« Règles de correspondance des actifs ») : identifiant d'instance
  égal à l'UUID de l'actif, UUID d'une machine virtuelle de l'inventaire (l'hôte est proposé), numéro
  de série, nom (identifiant ou étiquette `name`), étiquette `glpi_id` (`12` ou `Computer:12`).
- Une règle qui trouve plusieurs actifs ne propose rien (« Plusieurs actifs correspondent ») et laisse
  la main à la suivante. Rien ne se rattache tout seul : on coche les propositions justes puis on
  confirme, en masse ou depuis la fiche de la ressource ; chaque proposition est recalculée et
  vérifiée (entité de la ressource, droit de lire l'actif) au moment de la confirmation.

### Fixed

- **Ajout d'une plateforme Sangfor SCP derrière un pare-feu applicatif** (issue #48) : le formulaire
  envoyait l'adresse complète (`https://10.0.0.1:4430`) dans un champ `url`, motif qu'un pare-feu
  applicatif (par exemple FortiWeb, signature « RFI Injection ») peut prendre pour une inclusion de
  fichier distant et bloquer. Le nom (ou l'adresse IP) et le port se saisissent désormais dans deux
  champs séparés, `https://` est affiché devant et imposé par le serveur : aucune URL ne part dans la
  requête. Les plateformes déjà enregistrées ne changent pas ; les contrôles de l'adresse restent les
  mêmes (https seulement, sans chemin, paramètre ni identifiant).

## [0.2.0] - 2026-11-27

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

- **Version du plugin** (issue #38, composant commun parime/glpi-plugin-template#12). En tête de
  l'onglet Général de la configuration : version installée, dernière version publiée sur GitHub et
  badge « À jour », « Mise à jour disponible » (avec les notes de version et l'archive) ou
  « Dernière version inconnue » avec la raison. Délai de 3 s, résultat gardé 12 h, dernier résultat
  réussi réutilisé si GitHub ne répond pas, proxy de GLPI ; réglage « Vérifier les nouvelles
  versions sur GitHub » pour un serveur sans accès à Internet.

### Changed

- Contrôles de sécurité communs v1.11.3 (écart `config-version-premier` : l'encadré « Version du
  plugin » en premier bloc de la configuration, parime/glpi-plugin-template#18).

- **Configuration** : l'encadré « Version du plugin » est le premier bloc de l'onglet Général,
  au-dessus de la carte des réglages (charte du modèle, section 9 bis, issue 18 du modèle :
  parime/glpi-plugin-template#18).

- CI : contrôles de sécurité communs glpi-plugin-security v1.10.2, qui rétablit le contrôle
  d'ergonomie (réglages, menus, en-têtes), en avertissement le temps de l'issue de navigation #37.

- **Navigation** (issue #37) : la page de configuration (Configuration > Plugins > FinOps) ne
  contient plus de lien vers les écrans du quotidien ; l'onglet À propos indique le menu
  Gestion > FinOps (coûts, ressources, import) par une phrase.
- Les liens de GLPI vers une ressource, une ligne de coût ou un import (historique, recherche)
  ouvrent les pages Ressources, Coûts ou Import du plugin, et non plus le formulaire générique de
  GLPI, vide pour ces objets.
- Badges aux couleurs de thème (`bg-*-subtle text-*-emphasis`), lisibles au contraste WCAG AA ;
  le bouton « Ouvrir » de l'actif rattaché à une ressource se distingue sans la couleur.
- CI : contrôle d'ergonomie et parcours de toutes les pages de nouveau bloquants.

- CI : contrôles de sécurité communs glpi-plugin-security v1.10.1 (secrets, dépendances, CodeQL,
  test de mise à jour depuis la version publiée, tests d'intégration en français, parcours de toutes
  les pages en avertissement le temps de l'issue de navigation #37).

- CI : contrôles de sécurité communs glpi-plugin-security v1.11.1 (encadré « Version du plugin »
  exigé sur la page de configuration). Une PR qui ne touche que la documentation saute les
  contrôles qui démarrent un vrai GLPI (`heavy-paths`) ; un nouveau commit sur une PR annule
  l'exécution précédente de la CI et des contrôles communs, jamais sur un push de main ou dev.

- **Contrôles de sécurité communs v1.12.2** : éléments du cœur de GLPI reconnus par le parcours des pages.

- **Contrôles de sécurité communs v1.12.1** : parcours des pages en tranches parallèles (CI plus
  rapide).

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

