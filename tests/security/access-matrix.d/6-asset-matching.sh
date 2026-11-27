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

# Issue #6 : rattachement assisté, avec les données de setup.d/6-asset-matching.php.
# Fragment de tests/security/access-matrix.sh.

if [ "$ROLE" != none ]; then
  list=$(page "$P/resource.php?search=sec-prop")
  # Ordinateur de A proposé pour sa ressource ; celui de B jamais, même au super-admin.
  contains "propositions : ordinateur de A" "PC propose FinOps A" oui "$list"
  contains "propositions : ordinateur de B" "PC propose FinOps B" non "$list"
  contains "proposition sur la fiche de la ressource" "PC propose FinOps A" oui     "$(page "$P/resource.php?id=$(id prop_resource_a)")"
fi

# Validation : droit UPDATE ; jamais vers un actif qui n'est pas la proposition.
case $ROLE in none|reader)
  expect "valider une proposition sans droit" 403     "$(code -d "validate=1&proposals%5B$(id prop_resource_a)%5D=Computer:$(id proposed_computer_A)&_glpi_csrf_token=$(csrf)"       "$P/resource.php")" ;;
esac
if [ "$ROLE" = manager ]; then
  code -d "validate=1&proposals%5B$(id prop_resource_b)%5D=Computer:$(id proposed_computer_B)&_glpi_csrf_token=$(csrf)"     "$P/resource.php" > /dev/null
  contains "validation vers l'ordinateur de B refusée" "PC propose FinOps B" non     "$(page "$P/resource.php?id=$(id prop_resource_b)")"
fi
