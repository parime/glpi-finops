# Tests de sécurité de bout en bout

Lancés par la CI (job GLPI 12) dans une instance GLPI jetable :

| Script | Rôle |
|---|---|
| `setup.php` | crée le jeu de données (entités A et B, profils gestionnaire, lecteur et sans droit) et affiche les identifiants en JSON ; `setup.php xss '<json>'` crée les données piégées ; `setup.php counts` compte les lignes des tables du plugin |
| `access-matrix.sh` | matrice de contrôle d'accès, une fois par profil (`none`, `reader`, `manager`, `admin`) |
| `xss-check.sh` | vérifie qu'aucune page ne restitue la charge piégée sans échappement |
| `not-covered.txt` | pages de `front/` volontairement non testées, avec la raison |

## Ajouter les tests d'une fonctionnalité : un nouveau fichier par script

Ne pas allonger les scripts ci-dessus : toutes les branches les modifieraient en même temps. Une
fonctionnalité ajoute ses propres fichiers, nommés `<issue>-<sujet>` (exemple : `2-cost-import`) :

| Dossier | Chargé par | Contenu |
|---|---|---|
| `setup.d/<issue>-<sujet>.php` | `setup.php` | données des entités A et B ; ajoute ses identifiants à `$ids` |
| `setup-xss.d/<issue>-<sujet>.php` | `setup.php xss` | données piégées avec `$canary` ; ajoute ses identifiants à `$out` |
| `access-matrix.d/<issue>-<sujet>.sh` | `access-matrix.sh` | contrôles par profil (`case $ROLE in ...`) |
| `xss-check.d/<issue>-<sujet>.sh` | `xss-check.sh` | `check "<libellé>" "$P/<page>"` |

Variables et fonctions visibles dans les fragments :

- `setup.php` : `$DB`, `$ids`, `$a` et `$b` (entités), `$add($objet, $champs)`. Le script est relancé :
  un fragment vérifie d'abord que ses données n'existent pas.
- `setup.php xss` : `$DB`, `$ids`, `$out`, `$canary`, `$insert($table, $ligne)`,
  `$update($table, $ligne, $id)` (tables sans le préfixe `glpi_plugin_finops_`).
- `access-matrix.sh` : `$ROLE`, `$P` (URL de `front/`), `id <clé>`, `code`, `body`, `page`, `csrf`,
  `expect <libellé> <attendu> <obtenu>`, `contains <libellé> <texte> oui|non <contenu>`,
  `tab <itemtype> <onglet> <id>`, `readable <pages...>`, `$SEE_B` et `$DENY`.
- `xss-check.sh` : `$P`, `id`, `xid`, `check <libellé> <url>`, `tab`.

Le contrôle commun de couverture (glpi-plugin-security v1.9.1) trouve le chemin d'une page
(`front/<page>.php`) dans `access-matrix.sh` ou dans un fragment de `access-matrix.d/`.
