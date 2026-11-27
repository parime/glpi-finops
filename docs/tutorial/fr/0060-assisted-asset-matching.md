### Valider les rattachements proposés aux actifs de GLPI

1. **Configuration > Plugins > FinOps**, onglet **Général** : le champ **Règles de correspondance
   des actifs** donne l'ordre des règles (par défaut
   `instance_id, vm_uuid, serial, name, glpi_id_tag`). Retirer une règle la désactive ; un champ vide
   coupe les propositions.
2. Créer dans GLPI un ordinateur de test dont le **numéro de série** est `SN-DEMO-01`, puis importer
   un fichier de coûts (données fictives) :

   ```csv
   date,provider,account,service,resource,cost,currency,tags
   2026-10-01,ovhcloud,projet-demo,Public Cloud,SN-DEMO-01,3.10,EUR,
   2026-10-01,aws,123456789012,Amazon EC2,i-0demo02,12.40,USD,glpi_id=Computer:12
   ```

3. **Gestion > FinOps > Ressources** : les ressources non rattachées affichent **Proposé :** et
   l'actif trouvé, avec la règle (**Trouvé par : Numéro de série**). « Plusieurs actifs
   correspondent » signale une règle ambiguë : rattacher cette ressource à la main.
4. Cocher les propositions justes (la case de l'en-tête les coche toutes), puis
   **Rattacher les ressources cochées aux actifs proposés**. Une seule ressource : ouvrir sa fiche,
   vérifier l'actif avec **Ouvrir l'actif proposé**, puis **Confirmer la proposition**.
5. La carte **Ressources non rattachées** de la page Coûts donne ce qui reste à traiter.
