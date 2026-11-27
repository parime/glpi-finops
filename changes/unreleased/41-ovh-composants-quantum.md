### Fixed

- **OVHcloud : coûts de consommation Public Cloud perdus** (issue #41) : dans
  `/cloud/project/{id}/usage/history/{usageId}`, les prix de `resourcesUsage` sont portés par
  `resources[].components[]`, qui n'était pas parcouru (lignes rejetées, coûts manquants), et
  `hourlyUsage.quantum` est un objet (`notebook`, `processingUnit`) qui était écarté. Les deux sont
  désormais lus, d'après le modèle officiel `cloud.usage.UsageHistoryDetail` ; un composant donne une
  ligne par composant (libellé « type nom », ressource `resourceId`).
