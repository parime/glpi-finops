#!/usr/bin/env bash

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

# Test XSS de bout en bout : apres `setup.php xss`, chaque champ texte libre contient
# <svg/onload=alert('xss-canary')>. Aucune page ni onglet ne doit restituer cette charge telle
# quelle (non echappee).
#
# Usage : xss-check.sh <url GLPI> <json de setup.php> <json de setup.php xss>
# Une fonctionnalite ajoute ses pages dans tests/security/xss-check.d/<issue>-<sujet>.sh.
set -u
BASE=$1 IDS=$2 XSS=$3
CANARY="<svg/onload=alert('xss-canary')>"
JAR=$(mktemp)
FAIL=0
RENDERED=0
# Au moins une page affichée (échappée) par fragment : sinon les données piégées ne sont pas lues.
MIN_RENDERED=${MIN_RENDERED:-$(find "$(dirname "${BASH_SOURCE[0]}")/xss-check.d" -name '*.sh' | wc -l)}
id() { jq -r ".$1" <<< "$IDS"; }
xid() { jq -r ".$1" <<< "$XSS"; }
check() { # libelle, url
  local body status
  body=$(curl -s -w '\n%{http_code}' -c "$JAR" -b "$JAR" -H 'X-Requested-With: XMLHttpRequest' "$2")
  status=${body##*$'\n'}
  body=${body%$'\n'*}
  if [ "$status" != 200 ]; then
    echo "::error::$1 : code HTTP $status (page non verifiee)"; FAIL=1; return
  fi
  if grep -qF "$CANARY" <<< "$body"; then
    echo "::error::$1 : charge XSS restituee sans echappement"; FAIL=1; return
  fi
  grep -q "xss-canary" <<< "$body" && RENDERED=$((RENDERED + 1))
  echo "OK  $1"
}
tab() { # itemtype, onglet (classe$numero), id
  echo "$BASE/ajax/common.tabs.php?_target=%2Ffront%2Fx.php&_itemtype=$1&_glpi_tab=$(sed 's/\$/%24/' <<< "$2")&id=$3"
}

for try in 1 2 3 4 5; do
  rm -f "$JAR"
  token=$(curl -s -c "$JAR" -b "$JAR" "$BASE/" | grep -o 'name="_glpi_csrf_token" value="[^"]*"' \
    | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "login_name=glpi" --data-urlencode "login_password=glpi" \
    --data-urlencode "_glpi_csrf_token=$token" -d noAUTO=1 "$BASE/front/login.php"
  [ "$(curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$BASE/front/central.php")" = 200 ] && break
  [ "$try" = 5 ] && { echo "::error::connexion impossible"; exit 1; }
  sleep 3
done

P=$BASE/plugins/finops/front
check "page d'accueil" "$P/overview.php"
check "configuration, onglet general" "$P/config.php"

# Fragments : une fonctionnalite ajoute ses pages dans un NOUVEAU fichier
# tests/security/xss-check.d/<issue>-<sujet>.sh (fonctions et variables : P, id, xid, check, tab).
for fragment in "$(dirname "${BASH_SOURCE[0]}")"/xss-check.d/*.sh; do
  [ -e "$fragment" ] || continue
  # shellcheck source=/dev/null
  . "$fragment"
done

rm -f "$JAR"
if [ "$RENDERED" -lt "$MIN_RENDERED" ]; then
  echo "::error::la charge n'apparait (echappee) que sur $RENDERED page(s) : les donnees piegees ne sont pas affichees"
  FAIL=1
else
  echo "OK  charge affichee echappee sur $RENDERED page(s)"
fi
exit $FAIL
