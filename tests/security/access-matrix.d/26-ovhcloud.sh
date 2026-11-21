#
# -------------------------------------------------------------------------
# FinOps plugin for GLPI
# Copyright (C) 2026 Vincent GUILLOTTE
# https://github.com/parime/glpi-finops
# -------------------------------------------------------------------------
# LICENSE
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version. See LICENSE for the full text.
# -------------------------------------------------------------------------
#

# Issue #26 : comptes OVHcloud. Aucune lecture réelle n'est lancée (elle appellerait OVHcloud).
# Fragment de tests/security/access-matrix.sh.

# Page d'import : comptes des entités de la session seulement, aucune clé affichée.
if [ "$ROLE" = manager ] || [ "$ROLE" = admin ]; then
  imp=$(page "$P/import.php")
  contains "import : compte OVHcloud de A" "finops-ovh-securite-a" oui "$imp"
  contains "import : compte OVHcloud de B" "finops-ovh-securite-b" $SEE_B "$imp"
  contains "import : aucune clé OVHcloud en clair" "securiteAppSecret" non "$imp"
fi
if [ "$ROLE" = admin ]; then
  conf=$(page "$P/config.php?tab=sources")
  contains "configuration : comptes OVHcloud" "finops-ovh-securite-a" oui "$conf"
  contains "configuration : aucune clé OVHcloud en clair" "securiteConsumer" non "$conf"
fi

# Lecture : droit d'import du plugin et entité du compte.
case $ROLE in none|reader)
  expect "front/ovh.php : lecture du compte de A" 403 \
    "$(code -d "read=1&id=$(id ovh_A)&_glpi_csrf_token=$(csrf)" "$P/ovh.php")" ;;
esac
[ "$ROLE" != admin ] && expect "front/ovh.php : lecture du compte de B" 403 \
  "$(code -d "read=1&id=$(id ovh_B)&_glpi_csrf_token=$(csrf)" "$P/ovh.php")"
expect "front/ovh.php : lecture sans jeton CSRF" 403 \
  "$(code -H 'Sec-Fetch-Site: cross-site' -d "read=1&id=$(id ovh_A)" "$P/ovh.php")"

# Gestion des comptes : droit de configuration de GLPI (aucun des profils de test sauf super-admin).
if [ "$ROLE" != admin ]; then
  expect "front/ovh.php : ajout d'un compte" 403 \
    "$(code -d "add=1&name=x&entities_id=$(id a)&region=eu&_glpi_csrf_token=$(csrf)" "$P/ovh.php")"
  expect "front/ovh.php : modification du compte de A" 403 \
    "$(code -d "update=1&id=$(id ovh_A)&name=x&_glpi_csrf_token=$(csrf)" "$P/ovh.php")"
  expect "front/ovh.php : suppression du compte de A" 403 \
    "$(code -d "purge=1&id=$(id ovh_A)&_glpi_csrf_token=$(csrf)" "$P/ovh.php")"
fi
