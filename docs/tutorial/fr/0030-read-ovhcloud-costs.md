### Lire les coûts d'un compte OVHcloud

1. Sur l'API OVHcloud de votre région (Europe ou Canada), créer une application et une consumer key
   avec seulement des droits **GET** sur `/me`, `/me/bill/*` et `/cloud/project/*/usage/*`. Le plugin
   ne modifie jamais rien chez OVHcloud.
2. **Configuration > Plugins > FinOps**, onglet **Sources de coûts**, carte **Comptes OVHcloud** :
   **Ajouter un compte OVHcloud**, donner un nom, l'entité, la région, les trois clés et, si besoin,
   les identifiants des projets Public Cloud (32 caractères). Les clés sont chiffrées par GLPI et ne
   sont plus jamais affichées ; pour en changer une, la saisir de nouveau, un champ vide la garde.
3. **Gestion > FinOps > Import**, carte **Lire depuis OVHcloud** : cliquer sur **Lire maintenant**.
   Les factures et la consommation des projets listés arrivent dans les coûts, comme un import CSV.
   Relancer la lecture ne crée aucun doublon.
4. Si la lecture échoue (clés refusées, réseau, OVHcloud indisponible), rien n'est écrit : les coûts
   déjà lus restent, la carte affiche le motif et la date de la dernière réussite.
