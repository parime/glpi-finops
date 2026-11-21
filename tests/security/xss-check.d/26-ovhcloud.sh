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

# Issue #26 : comptes OVHcloud piégés (setup-xss.d/26-ovhcloud.php).
check "comptes OVHcloud de la configuration" "$P/config.php?tab=sources"
check "lecture OVHcloud de la page d'import" "$P/import.php"
