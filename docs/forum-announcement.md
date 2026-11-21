# Ébauche d'annonce pour le forum GLPI

Brouillon, **non publié**. À poster dans la catégorie « Plugins » du forum GLPI quand la première
version sera publiée (archive et fiche du catalogue). Remplacer `<version>` et ajouter une ou deux
captures (coûts par fournisseur et par mois, import avec aperçu).

---

## English

**Title:** [Plugin] FinOps: cost of cloud and virtualised resources inside GLPI (GLPI 11 and 12)

Hello everyone,

I am publishing **FinOps** `<version>`, a free plugin (GPL-3.0-or-later) that brings the cost of
cloud and virtualised resources into GLPI, next to the assets they pay for.

**What it does today**

- Imports a cost file in a common CSV format (date, provider, account or subscription, service,
  resource, cost, currency, tags). Any billing export (AWS, Azure, Google Cloud, OVHcloud, a hosting
  company, a hypervisor) can be brought back to it.
- Safe reading: size limit, binary and booby-trapped files refused, preview before writing,
  re-runnable import without duplicates, import log.
- Costs by provider and by month, one table per currency.
- Links a cloud resource (instance, VM, database) to a GLPI computer, appliance or network device,
  with a FinOps tab on the asset.
- Entities respected, a single menu entry (Management > FinOps), all settings in the plugin page,
  18 languages.

**What comes next** (issues open for discussion): AWS, Azure, Google Cloud, OVHcloud and Scaleway
connectors (read-only, encrypted secrets), Proxmox / Hyper-V / VMware costs, showback per entity,
under-used "zombie" VMs, budgets and overspending alerts. Sangfor and H3C sources are open to
contributions from people who run them.

- Code and roadmap: https://github.com/parime/glpi-finops
- Compatibility: GLPI 11 and 12, PHP 8.2 to 8.5.

Feedback on the CSV format and on the billing exports you would like to import is very welcome.

---

## Français

**Titre :** [Plugin] FinOps : le coût des ressources cloud et virtualisées dans GLPI (GLPI 11 et 12)

Bonjour à tous,

Je publie **FinOps** `<version>`, un plugin libre (GPL-3.0-or-later) qui ramène le coût des
ressources cloud et virtualisées dans GLPI, à côté des actifs qu'elles servent.

**Ce qu'il fait aujourd'hui**

- Import d'un fichier de coûts au format CSV commun (date, fournisseur, compte ou abonnement,
  service, ressource, coût, devise, étiquettes). Tout export de facturation (AWS, Azure, Google
  Cloud, OVHcloud, un hébergeur, un hyperviseur) peut y être ramené.
- Lecture sûre : taille bornée, fichiers binaires ou piégés refusés, aperçu avant écriture, import
  rejouable sans doublon, journal des imports.
- Coûts par fournisseur et par mois, une table par devise.
- Rattachement d'une ressource cloud (instance, VM, base) à un ordinateur, une application ou un
  matériel réseau de GLPI, avec un onglet FinOps sur l'actif.
- Entités respectées, une seule entrée de menu (Gestion > FinOps), tous les réglages dans la page du
  plugin, 18 langues.

**La suite** (issues ouvertes à la discussion) : connecteurs AWS, Azure, Google Cloud, OVHcloud et
Scaleway (lecture seule, secrets chiffrés), coûts Proxmox / Hyper-V / VMware, showback par entité,
VM « zombies » sous-utilisées, budgets et alertes de surcoût. Les sources Sangfor et H3C attendent
les contributions de ceux qui les exploitent.

- Code et feuille de route : https://github.com/parime/glpi-finops
- Compatibilité : GLPI 11 et 12, PHP 8.2 à 8.5.

Vos retours sur le format CSV et sur les exports de facturation que vous aimeriez importer sont les
bienvenus.
