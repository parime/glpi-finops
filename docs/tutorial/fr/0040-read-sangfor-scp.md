### Lire une plateforme Sangfor HCI (SCP)

1. Sur Sangfor SCP (6.3.0 ou plus, la console qui voit tous les nœuds), créer un compte
   d'opérateur avec seulement la **lecture** des machines virtuelles et des hôtes. Le plugin ne
   démarre, n'arrête et ne modifie jamais rien. aSV seul, sans SCP, n'est pas encore lu.
2. **Configuration > Plugins > FinOps**, onglet **Sources de coûts**, carte **Plateformes Sangfor
   SCP** : **Ajouter une plateforme Sangfor SCP**, donner un nom, l'entité, l'adresse
   (`https://scp.example.org`, sans chemin), l'identifiant et le mot de passe (chiffrés par GLPI,
   plus jamais affichés). Laisser **Accepter un certificat auto-signé** décoché, sauf si SCP garde
   son certificat d'usine : installer l'autorité du certificat sur le serveur GLPI est plus sûr.
3. Remplir la **grille de prix** (par mois : un vCPU, 1 Go de mémoire, 1 Go de disque) pour
   obtenir un coût, ou laisser 0 pour un simple inventaire. vCPU et mémoire ne comptent que si la
   VM n'est pas éteinte ; le disque compte toujours.
4. **Gestion > FinOps > Import**, carte **Lire depuis Sangfor SCP** : **Lire maintenant**. La page
   d'inventaire s'ouvre : nœuds, VM, allocations et coût du mois. Relire ne crée aucun doublon ;
   le coût d'un mois est fixé par sa première lecture.
5. Dans la colonne **Actif dans GLPI**, vérifier l'ordinateur proposé (même UUID ou même nom) et
   cliquer sur **Rattacher**. Sans proposition, **Choisir un actif** ouvre la page des ressources.
6. Si la lecture échoue (connexion refusée, certificat, réseau), rien n'est écrit : l'inventaire et
   les coûts déjà lus restent, la carte affiche le motif et la date de la dernière réussite.
