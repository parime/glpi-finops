[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

<!-- Fichier généré par tools/build_tutorial.py à partir de docs/tutorial/ (à la publication) :
     ne pas le modifier à la main, ajouter ou modifier un fragment. -->

## 🇫🇷 Français

Ce tutoriel suit pas à pas un administrateur qui découvre FinOps : installation, réglages, puis
suivi des coûts. Toutes les données citées sont fictives.

### Étape 1 : Installer le plugin et le régler

1. Extraire l'archive dans `plugins/` (dossier `finops`), puis **Configuration > Plugins** :
   installer et activer **FinOps**.
2. Ouvrir la page du plugin (clic sur son nom dans **Configuration > Plugins**). Onglet
   **Général** : choisir la **devise par défaut** (par exemple `EUR`), utilisée quand une ligne de
   coût n'en donne pas, et la **taille maximale d'un fichier** (5 Mo par défaut). Enregistrer.
3. Onglet **Sources de coûts** : la liste des sources disponibles (pour commencer, le fichier CSV
   générique) ; les connecteurs prévus apparaîtront ici.
4. Donner l'accès aux autres profils : **Administration > Profils > (profil) > onglet FinOps**.
   Lire suffit pour consulter les coûts ; importer, rattacher et supprimer un import sont des droits
   séparés.
5. Le plugin a une seule entrée de menu : **Gestion > FinOps**.

### Étape 2 : Importer des coûts et rattacher une ressource à un ordinateur

1. Préparer un fichier CSV (données fictives) dont la première ligne donne les noms des colonnes :

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo01,12.40,USD,team=web;env=prod
   2026-10-01,ovhcloud,projet-demo,Public Cloud,b2-7-demo,3.10,EUR,
   ```

   Les colonnes `date`, `provider` et `cost` sont obligatoires ; d'autres noms sont acceptés
   (`subscription`, `amount`, `resource_id`...). Séparateur virgule, point-virgule ou tabulation.
2. **Gestion > FinOps > Import** : choisir l'entité, déposer le fichier, cliquer sur **Aperçu**.
   L'aperçu compte les lignes nouvelles, déjà importées et rejetées (avec le numéro de ligne et la
   raison) ; rien n'est encore écrit.
3. Cliquer sur **Importer les nouvelles lignes**. Relancer le même fichier ne crée aucun doublon ;
   le journal en bas de page garde chaque import, que l'on peut supprimer avec ses lignes.
4. **Gestion > FinOps > Coûts** : une table par devise, les mois en lignes, les fournisseurs en
   colonnes. La carte orange compte les ressources pas encore rattachées à un actif.
5. **Gestion > FinOps > Ressources** : cliquer sur une ressource marquée « Non rattachée », choisir
   le type d'actif (ordinateur, application, matériel réseau) et l'actif, puis **Rattacher**.
   L'ordinateur reçoit un onglet **FinOps** avec ses ressources et leur coût par mois.

### Étape 3 : Lire les coûts d'un compte OVHcloud

1. Sur l'API OVHcloud de votre région (Europe ou Canada), créer une application et une consumer key
   avec seulement des droits **GET** sur `/me`, `/me/bill/*` et `/cloud/project/*/usage/*`. Le plugin
   ne modifie jamais rien chez OVHcloud.
2. **Configuration > Plugins > FinOps**, onglet **Sources de coûts**, carte **Comptes OVHcloud** :
   **Ajouter un compte OVHcloud**, donner un nom, l'entité, la région, les trois clés et, si besoin,
   les identifiants des projets Public Cloud (32 caractères). Les clés sont chiffrées par GLPI et ne
   sont plus jamais affichées ; pour en changer une, la saisir de nouveau, un champ vide la garde.
3. **Gestion > FinOps > Import**, carte **Lire depuis OVHcloud** : cliquer sur **Lire maintenant**.
   Les factures et la consommation des projets listés arrivent dans les coûts, comme un import CSV.
   Relancer la lecture ne crée aucun doublon.
4. Si la lecture échoue (clés refusées, réseau, OVHcloud indisponible), rien n'est écrit : les coûts
   déjà lus restent, la carte affiche le motif et la date de la dernière réussite.

### Étape 4 : Lire une plateforme Sangfor HCI (SCP)

1. Sur Sangfor SCP (6.3.0 ou plus, la console qui voit tous les nœuds), créer un compte
   d'opérateur avec seulement la **lecture** des machines virtuelles et des hôtes. Le plugin ne
   démarre, n'arrête et ne modifie jamais rien. aSV seul, sans SCP, n'est pas encore lu.
2. **Configuration > Plugins > FinOps**, onglet **Sources de coûts**, carte **Plateformes Sangfor
   SCP** : **Ajouter une plateforme Sangfor SCP**, donner un nom, l'entité, l'adresse
   (`https://scp.example.org`, sans chemin), l'identifiant et le mot de passe (chiffrés par GLPI,
   plus jamais affichés). Laisser **Accepter un certificat auto-signé** décoché, sauf si SCP garde
   son certificat d'usine : installer l'autorité du certificat sur le serveur GLPI est plus sûr.
3. Remplir la **grille de prix** (par mois : un vCPU, 1 Go de mémoire, 1 Go de disque) pour
   obtenir un coût, ou laisser 0 pour un simple inventaire. vCPU et mémoire ne comptent que si la
   VM n'est pas éteinte ; le disque compte toujours.
4. **Gestion > FinOps > Import**, carte **Lire depuis Sangfor SCP** : **Lire maintenant**. La page
   d'inventaire s'ouvre : nœuds, VM, allocations et coût du mois. Relire ne crée aucun doublon ;
   le coût d'un mois est fixé par sa première lecture.
5. Dans la colonne **Actif dans GLPI**, vérifier l'ordinateur proposé (même UUID ou même nom) et
   cliquer sur **Rattacher**. Sans proposition, **Choisir un actif** ouvre la page des ressources.
6. Si la lecture échoue (connexion refusée, certificat, réseau), rien n'est écrit : l'inventaire et
   les coûts déjà lus restent, la carte affiche le motif et la date de la dernière réussite.

### Étape 5 : Vérifier l'étiquetage obligatoire et les propriétaires

1. **Configuration > Plugins > FinOps**, onglet **Général** : saisir les **étiquettes obligatoires**
   séparées par des virgules (par défaut `owner, cost-center, env`) et l'**étiquette du
   propriétaire** (par défaut `owner`), puis **Sauvegarder**. Un champ vide désactive le contrôle.
2. Importer un fichier de coûts dont la colonne `tags` porte ces étiquettes (données fictives) :

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo01,12.40,USD,owner=a.martin;cost-center=CC42;env=prod
   2026-10-01,aws,123456789012,Amazon S3,bucket-demo,1.10,USD,env=dev
   ```

3. **Gestion > FinOps > Ressources** : la carte **Étiquettes obligatoires** donne le taux de
   conformité, puis le détail par fournisseur et par compte. Le propriétaire apparaît en vert quand
   sa valeur est l'identifiant de connexion ou le courriel d'un utilisateur de GLPI, en orange
   sinon, en rouge quand l'étiquette manque.
4. Activer **Étiquetage à corriger seulement** pour lister les ressources à reprendre. Les étiquettes
   se corrigent chez le fournisseur : elles sont relues au prochain import.

## 🇬🇧 English

This tutorial follows, step by step, an administrator discovering FinOps: installation, settings,
then cost tracking. All the data quoted is fictitious.

### Step 1: Install the plugin and set it up

1. Extract the archive into `plugins/` (folder `finops`), then **Setup > Plugins**: install and
   enable **FinOps**.
2. Open the plugin page (click its name in **Setup > Plugins**). **General** tab: choose the
   **default currency** (for example `EUR`), used when a cost line gives none, and the **largest
   file accepted** (5 MB by default). Save.
3. **Cost sources** tab: the list of available sources (to begin with, the generic CSV file); the
   planned connectors will show up here.
4. Give access to other profiles: **Administration > Profiles > (profile) > FinOps tab**. Read is
   enough to see the costs; importing, linking and deleting an import are separate rights.
5. The plugin has a single menu entry: **Management > FinOps**.

### Step 2: Import costs and link a resource to a computer

1. Prepare a CSV file (fictitious data) whose first line gives the column names:

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo01,12.40,USD,team=web;env=prod
   2026-10-01,ovhcloud,projet-demo,Public Cloud,b2-7-demo,3.10,EUR,
   ```

   The `date`, `provider` and `cost` columns are required; other names are accepted
   (`subscription`, `amount`, `resource_id`...). Separator: comma, semicolon or tab.
2. **Management > FinOps > Import**: choose the entity, upload the file, click **Preview**. The
   preview counts the new, already imported and rejected lines (with the line number and the
   reason); nothing is written yet.
3. Click **Import the new lines**. Importing the same file again creates no duplicate; the log at
   the bottom of the page keeps every import, which can be deleted with its lines.
4. **Management > FinOps > Costs**: one table per currency, months as rows, providers as columns.
   The orange card counts the resources not yet linked to an asset.
5. **Management > FinOps > Resources**: click a resource marked "Not linked", choose the asset type
   (computer, appliance, network device) and the asset, then **Link**. The computer gets a
   **FinOps** tab with its resources and their cost by month.

### Step 3: Read the costs of an OVHcloud account

1. On the OVHcloud API of your region (Europe or Canada), create an application and a consumer key
   with only **GET** rights on `/me`, `/me/bill/*` and `/cloud/project/*/usage/*`. The plugin never
   changes anything at OVHcloud.
2. **Setup > Plugins > FinOps**, **Cost sources** tab, **OVHcloud accounts** card: **Add an OVHcloud
   account**, give a name, the entity, the region, the three keys and, if needed, the identifiers of
   the Public Cloud projects (32 characters). The keys are encrypted by GLPI and never shown again;
   to change one, type it again, an empty field keeps it.
3. **Management > FinOps > Import**, **Read from OVHcloud** card: click **Read now**. The bills and
   the consumption of the listed projects come into the costs, like a CSV import. Reading again
   creates no duplicate.
4. If the read fails (keys refused, network, OVHcloud unavailable), nothing is written: the costs
   already read stay, the card shows the reason and the date of the last success.

### Step 4: Read a Sangfor HCI platform (SCP)

1. On Sangfor SCP (6.3.0 or later, the console that sees all the nodes), create an operator
   account with only **read** rights on the virtual machines and the hosts. The plugin never
   starts, stops or changes anything. A single aSV node, without SCP, is not read yet.
2. **Setup > Plugins > FinOps**, **Cost sources** tab, **Sangfor SCP platforms** card: **Add a
   Sangfor SCP platform**, give a name, the entity, the address (`https://scp.example.org`, no
   path), the user name and the password (encrypted by GLPI, never shown again). Leave **Accept a
   self-signed certificate** unticked, unless SCP keeps its factory certificate: installing the
   authority of the certificate on the GLPI server is safer.
3. Fill in the **price grid** (per month: one vCPU, 1 GB of memory, 1 GB of disk) to get a cost,
   or leave 0 for a plain inventory. vCPU and memory count only when the machine is not powered
   off; the disk always counts.
4. **Management > FinOps > Import**, **Read from Sangfor SCP** card: **Read now**. The inventory
   page opens: nodes, machines, allocations and cost of the month. Reading again creates no
   duplicate; the cost of a month is set by its first read.
5. In the **Asset in GLPI** column, check the proposed computer (same UUID or same name) and click
   **Link**. Without a proposal, **Choose an asset** opens the resources page.
6. If the read fails (login refused, certificate, network), nothing is written: the inventory and
   the costs already read stay, the card shows the reason and the date of the last success.

### Step 5: Check mandatory tags and owners

1. **Setup > Plugins > FinOps**, **General** tab: enter the **mandatory tags** separated by commas
   (by default `owner, cost-center, env`) and the **owner tag** (by default `owner`), then
   **Save**. An empty field turns the check off.
2. Import a cost file whose `tags` column carries these tags (fictitious data):

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo01,12.40,USD,owner=a.martin;cost-center=CC42;env=prod
   2026-10-01,aws,123456789012,Amazon S3,bucket-demo,1.10,USD,env=dev
   ```

3. **Management > FinOps > Resources**: the **Mandatory tags** card gives the compliance rate, then
   the detail by provider and by account. The owner is shown in green when its value is the login
   or email of a GLPI user, in orange otherwise, in red when the tag is missing.
4. Turn on **Tagging to fix only** to list the resources to rework. Tags are fixed on the provider
   side: they are read again at the next import.
