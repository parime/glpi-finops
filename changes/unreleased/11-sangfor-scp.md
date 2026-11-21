### Added

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

### Security

- Identifiant et mot de passe chiffrés par GLPI, jamais réaffichés ; mot de passe envoyé chiffré
  en RSA avec la clé publique de SCP, puis jeton. Seule l'adresse configurée est appelée (https
  obligatoire, aucune redirection suivie, aucune adresse reprise d'une réponse) ; le seul POST est
  l'authentification.
- Certificat de SCP vérifié par défaut ; un certificat auto-signé n'est accepté que si
  l'administrateur coche l'option de la plateforme, signalée par un badge.
- Une lecture en échec n'écrit rien (ni inventaire ni coût) et garde la lecture précédente ; le
  message affiché ne reprend ni les secrets ni le texte renvoyé par SCP.
