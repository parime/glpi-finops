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

# Issue #6 : rattachement assisté, avec les données piégées de setup-xss.d/6-asset-matching.php.
check "ressources : actif proposé au nom piégé" "$P/resource.php?search=xss-prop"
check "ressource : actif proposé au nom piégé" "$P/resource.php?id=$(xid proposal_resource)"
check "configuration : règles de correspondance" "$P/config.php"
