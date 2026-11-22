### Changed

- **Navigation** (issue #37) : la page de configuration (Configuration > Plugins > FinOps) ne
  contient plus de lien vers les écrans du quotidien ; l'onglet À propos indique le menu
  Gestion > FinOps (coûts, ressources, import) par une phrase.
- Les liens de GLPI vers une ressource, une ligne de coût ou un import (historique, recherche)
  ouvrent les pages Ressources, Coûts ou Import du plugin, et non plus le formulaire générique de
  GLPI, vide pour ces objets.
- Badges aux couleurs de thème (`bg-*-subtle text-*-emphasis`), lisibles au contraste WCAG AA ;
  le bouton « Ouvrir » de l'actif rattaché à une ressource se distingue sans la couleur.
- CI : contrôle d'ergonomie et parcours de toutes les pages de nouveau bloquants.
