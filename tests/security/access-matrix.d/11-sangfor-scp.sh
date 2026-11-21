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

# Issue #11 : plateformes Sangfor SCP. Aucune lecture réelle n'est lancée (elle appellerait SCP).
# Fragment de tests/security/access-matrix.sh.

# Inventaire : droit de lecture du plugin et entité de la plateforme.
readable "front/sangfor.php?id=$(id sangfor_A)"
[ "$ROLE" != none ] && expect "front/sangfor.php : inventaire de B" $DENY "$(code "$P/sangfor.php?id=$(id sangfor_B)")"
if [ "$ROLE" != none ]; then
  inv=$(page "$P/sangfor.php?id=$(id sangfor_A)")
  contains "inventaire : VM de A" "vm-scp-securite-a" oui "$inv"
  contains "inventaire : aucun mot de passe SCP en clair" "securiteScpPassword" non "$inv"
fi

# Page d'import : plateformes des entités de la session seulement, aucun secret affiché.
if [ "$ROLE" = manager ] || [ "$ROLE" = admin ]; then
  imp=$(page "$P/import.php")
  contains "import : plateforme SCP de A" "finops-scp-securite-a" oui "$imp"
  contains "import : plateforme SCP de B" "finops-scp-securite-b" $SEE_B "$imp"
  contains "import : aucun identifiant SCP en clair" "securiteScpUser" non "$imp"
fi
if [ "$ROLE" = admin ]; then
  conf=$(page "$P/config.php?tab=sources")
  contains "configuration : plateformes SCP" "finops-scp-securite-a" oui "$conf"
  contains "configuration : aucun mot de passe SCP en clair" "securiteScpPassword" non "$conf"
fi

# Lecture : droit d'import du plugin et entité de la plateforme.
case $ROLE in none|reader)
  expect "front/sangfor.php : lecture de la plateforme de A" 403 \
    "$(code -d "read=1&id=$(id sangfor_A)&_glpi_csrf_token=$(csrf)" "$P/sangfor.php")" ;;
esac
[ "$ROLE" != admin ] && expect "front/sangfor.php : lecture de la plateforme de B" 403 \
  "$(code -d "read=1&id=$(id sangfor_B)&_glpi_csrf_token=$(csrf)" "$P/sangfor.php")"
expect "front/sangfor.php : lecture sans jeton CSRF" 403 \
  "$(code -H 'Sec-Fetch-Site: cross-site' -d "read=1&id=$(id sangfor_A)" "$P/sangfor.php")"

# Rattachement : droit de modifier les ressources du plugin et entité.
case $ROLE in none|reader)
  expect "front/sangfor.php : rattacher une VM de A" 403 \
    "$(code -d "link=1&id=$(id sangfor_A)&vm=$(id sangforvm_A)&items_id=1&_glpi_csrf_token=$(csrf)" "$P/sangfor.php")" ;;
esac
[ "$ROLE" != admin ] && expect "front/sangfor.php : rattacher une VM de B" 403 \
  "$(code -d "link=1&id=$(id sangfor_B)&vm=$(id sangforvm_B)&items_id=1&_glpi_csrf_token=$(csrf)" "$P/sangfor.php")"

# Gestion des plateformes : droit de configuration de GLPI (aucun des profils de test sauf super-admin).
if [ "$ROLE" != admin ]; then
  expect "front/sangfor.php : ajout d'une plateforme" 403 \
    "$(code -d "add=1&name=x&entities_id=$(id a)&url=https://x.example.test&_glpi_csrf_token=$(csrf)" "$P/sangfor.php")"
  expect "front/sangfor.php : modification de la plateforme de A" 403 \
    "$(code -d "update=1&id=$(id sangfor_A)&name=x&_glpi_csrf_token=$(csrf)" "$P/sangfor.php")"
  expect "front/sangfor.php : suppression de la plateforme de A" 403 \
    "$(code -d "purge=1&id=$(id sangfor_A)&_glpi_csrf_token=$(csrf)" "$P/sangfor.php")"
fi
