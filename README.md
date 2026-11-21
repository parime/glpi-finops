# FinOps pour GLPI

[🇬🇧 English](README.en.md)

Plugin GLPI qui suit le **coût des ressources cloud et virtualisées** directement dans le parc :
coûts importés par fournisseur et par mois, ressources rattachées aux ordinateurs et applications de
GLPI, puis (feuille de route) showback par entité, ressources sous-utilisées et alertes de surcoût.

## Présentation

- **Import des coûts** depuis un fichier CSV au format commun (date, fournisseur, compte ou
  abonnement, service, ressource, coût, devise, étiquettes) : tout export de facturation AWS, Azure,
  Google Cloud, OVHcloud, d'un hébergeur ou d'un hyperviseur peut y être ramené. Aperçu avant
  écriture, import rejouable sans doublon, journal des imports.
- **Tableau par fournisseur et par mois**, une table par devise (le plugin n'additionne jamais deux
  devises).
- **Rattachement d'une ressource** (instance, VM, base...) à un actif de GLPI, avec un onglet
  « FinOps » sur l'actif.
- **Sources extensibles** : une source (AWS, Azure, GCP, OVHcloud, Proxmox, Hyper-V, VMware, Sangfor,
  H3C...) est une classe `src/Source/<Nom>Source.php`, découverte sans liste centrale.

Feuille de route : [issues du dépôt](https://github.com/parime/glpi-finops/issues).

## Principes

- **Lecture seule** vers les fournisseurs et les hyperviseurs : le plugin lit, il n'arrête, ne
  supprime ni ne modifie rien chez eux.
- **Rien ne change tout seul dans GLPI** : le plugin propose, un humain valide.
- **Entités respectées** : chaque ligne appartient à l'entité choisie à l'import ; chacun ne voit que
  les entités de sa session.
- **Aucun appel sortant** sans réglage explicite d'un administrateur ; secrets chiffrés.
- Réglages dans la page du plugin seulement, une seule entrée de menu (Gestion > FinOps).

Le plugin suit la [charte commune des plugins](https://github.com/parime/glpi-plugin-template/blob/main/CHARTE.md).

## Compatibilité

- GLPI 11 et 12 (un seul code source, voir `src/Compat/`).
- PHP 8.2 à 8.5 (GLPI 12 : 8.3 à 8.5).

## Installation

1. Télécharger l'archive de la dernière version (onglet *Releases*) et l'extraire dans `plugins/` :
   le dossier doit s'appeler `finops`.
2. Configuration > Plugins : installer puis activer **FinOps**.
3. Les profils qui modifient la configuration de GLPI reçoivent tous les droits du plugin ; les
   autres se règlent dans Administration > Profils > onglet **FinOps**.

## Utilisation

- **Configuration > Plugins > FinOps** : devise par défaut, taille maximale d'un fichier, sources de
  coûts disponibles.
- **Gestion > FinOps** : coûts par fournisseur et par mois, ressources, import.

Le tutoriel pas à pas est assemblé dans `docs/TUTORIAL.md` à chaque version, à partir de
`docs/tutorial/`.

## Documentation

- [CHANGELOG.md](CHANGELOG.md) : évolutions par version.
- [CONTRIBUTING.md](CONTRIBUTING.md) : contribuer, et ajouter une fonctionnalité sans conflit.
- [SECURITY.md](SECURITY.md) : signaler une vulnérabilité.
- [Charte commune des plugins](https://github.com/parime/glpi-plugin-template/blob/main/CHARTE.md).

## Réutilisation et citation

Ce plugin est sous **GPL-3.0-or-later**. Toute copie ou adaptation, même d'un seul fichier, garde
l'en-tête de licence de chaque fichier et le fichier `NOTICE`, reste sous GPL avec son code source,
et indique les modifications. Pour citer le projet, voir `CITATION.cff`.

## Licence

GNU General Public License v3.0 ou ultérieure : voir [LICENSE](LICENSE).
