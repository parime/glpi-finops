### Vérifier l'étiquetage obligatoire et les propriétaires

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
