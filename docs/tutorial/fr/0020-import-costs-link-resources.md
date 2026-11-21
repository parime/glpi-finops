### Importer des coûts et rattacher une ressource à un ordinateur

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
