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
