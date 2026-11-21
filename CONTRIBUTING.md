# Contribuer · Contributing

[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

## 🇫🇷 Français

Lire d'abord la [charte commune des plugins](https://github.com/parime/glpi-plugin-template/blob/main/CHARTE.md).

### Branches

| Branche | Rôle |
|---|---|
| `main` | versions publiées seulement |
| `dev` | développement en cours ; toute PR vise `dev` |
| `feat/<issue>-<sujet>`, `fix/<issue>-<sujet>` | une fonctionnalité ou un correctif, tirée de `dev` |

Commits, PR et issues en français ; code et identifiants en anglais. Données fictives uniquement.

### Ajouter une fonctionnalité sans conflit

Plusieurs fonctionnalités avancent en même temps : une fonctionnalité **crée de nouveaux fichiers**,
découverts automatiquement, au lieu d'allonger des listes centrales.

| Pour ajouter... | Créer | Ne pas modifier |
|---|---|---|
| ses tables | `src/Install/<Fonctionnalité>Tables.php` (interface `FeatureTables`) | `src/Install/Installer.php` |
| une source de coûts | `src/Source/<Nom>Source.php` (interface `CostSource`) | `src/Source/SourceRegistry.php` |
| ses tests de bout en bout | `tests/security/setup.d/`, `setup-xss.d/`, `access-matrix.d/`, `xss-check.d/` : `<issue>-<sujet>.php` ou `.sh` (voir `tests/security/README.md`) | `tests/security/setup.php`, `access-matrix.sh`, `xss-check.sh` |
| son entrée de CHANGELOG | `changes/unreleased/<issue>-<sujet>.md` | `CHANGELOG.md` |
| une étape du tutoriel | `docs/tutorial/fr/NNNN-mots.md` et `docs/tutorial/en/NNNN-mots.md` (même nom) | `docs/TUTORIAL.md` (généré) |

- Tables : `install()` et `migrate()` rejouables ; pas de SQL brut ailleurs.
- CHANGELOG : rubriques `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`,
  `Documentation` ; `python3 tools/build_changelog.py --stdout` montre le résultat.
- Tutoriel : la première ligne d'un fragment est `### <titre>` ; les numéros vont de 10 en 10.

### Vérifier avant de pousser

`composer install`, puis `vendor/bin/phpcs`, `vendor/bin/phpstan analyse` (dans une instance GLPI)
et `vendor/bin/phpunit`. Traductions : 7 langues de base, variantes générées par
`sh .github/scripts/sync-locale-variants.sh`.

## 🇬🇧 English

Read the [common charter of the plugins](https://github.com/parime/glpi-plugin-template/blob/main/CHARTE.md) first.

- Branches: `main` (releases only), `dev` (ongoing work, every PR targets `dev`), feature branches
  from `dev`.
- A feature adds new files instead of growing central lists: tables in
  `src/Install/<Feature>Tables.php` (`FeatureTables`), a cost source in `src/Source/<Name>Source.php`
  (`CostSource`), end-to-end security tests in `tests/security/*.d/`, a CHANGELOG fragment in
  `changes/unreleased/<issue>-<topic>.md`, a tutorial step in `docs/tutorial/{fr,en}/NNNN-*.md`.
- Fictitious data only. Commits and PRs in French.
