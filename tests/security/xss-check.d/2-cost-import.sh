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
check "coûts par fournisseur et par mois" "$P/overview.php"
check "liste des ressources" "$P/resource.php"
check "fiche de rattachement d'une ressource" "$P/resource.php?id=$(xid resource)"
check "journal des imports" "$P/import.php"
check "onglet FinOps d'un ordinateur" "$(tab Computer 'GlpiPlugin%5CFinops%5CResource$1' "$(id computer_A)")"
