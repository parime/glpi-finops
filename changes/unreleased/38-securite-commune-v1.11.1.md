### Changed

- CI : contrôles de sécurité communs glpi-plugin-security v1.11.1 (encadré « Version du plugin »
  exigé sur la page de configuration). Une PR qui ne touche que la documentation saute les
  contrôles qui démarrent un vrai GLPI (`heavy-paths`) ; un nouveau commit sur une PR annule
  l'exécution précédente de la CI et des contrôles communs, jamais sur un push de main ou dev.
