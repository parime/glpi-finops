[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

## 🇫🇷 Français

### Résumé

<!-- Quoi, et surtout pourquoi. Issue liée : #… -->

### Vérifications

- [ ] Testé dans une vraie instance GLPI 11 et 12 (Docker), en naviguant comme un utilisateur
- [ ] Données fictives uniquement (fichiers de coûts, comptes, ressources)
- [ ] Aucun appel d'écriture vers un fournisseur cloud ou un hyperviseur
- [ ] `vendor/bin/phpstan analyse --no-progress` sans erreur
- [ ] `vendor/bin/phpcs` sans erreur
- [ ] `vendor/bin/phpunit` vert
- [ ] Traductions ajoutées dans toutes les langues
- [ ] Fragment `changes/unreleased/<issue>-<sujet>.md` et, si pertinent, étape de tutoriel `docs/tutorial/{fr,en}/`

---

## 🇬🇧 English

### Summary

<!-- What, and mainly why. Linked issue: #… -->

### Checklist

- [ ] Tested in a real GLPI 11 and 12 instance (Docker), browsing as a user
- [ ] Fictitious data only (cost files, accounts, resources)
- [ ] No write call to a cloud provider or a hypervisor
- [ ] `vendor/bin/phpstan analyse --no-progress` clean
- [ ] `vendor/bin/phpcs` clean
- [ ] `vendor/bin/phpunit` green
- [ ] Translations added in every language
- [ ] Fragment `changes/unreleased/<issue>-<topic>.md` and, if relevant, tutorial step `docs/tutorial/{fr,en}/`
