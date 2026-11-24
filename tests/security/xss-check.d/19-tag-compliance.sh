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

# Issue #19 : étiquetage obligatoire, avec les données piégées de setup-xss.d/19-tag-compliance.php.
check "ressources : conformité de l'étiquetage" "$P/resource.php?tofix=1"
check "ressource aux étiquettes piégées" "$P/resource.php?id=$(xid tagged_resource)"
check "configuration : étiquettes obligatoires" "$P/config.php"
