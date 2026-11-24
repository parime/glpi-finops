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

# Issue #2 : pages et onglet de l'import des coûts, avec les données piégées de setup-xss.d/2-cost-import.php.

# Issue #19 : étiquetage obligatoire et propriétaire, sur la page Ressources (droit READ du plugin).
# Fragment de tests/security/access-matrix.sh.

case $ROLE in
  none) want=403 ;;
  *) want=200 ;;
esac
expect "front/resource.php : ressources à corriger" $want "$(code "$P/resource.php?tofix=1")"
if [ "$ROLE" != none ]; then
  # Filtre « à corriger » : jamais de ressource d'une autre entité.
  contains "ressources à corriger : ressource de B" "securite-vm-b" $SEE_B "$(page "$P/resource.php?tofix=1")"
fi
