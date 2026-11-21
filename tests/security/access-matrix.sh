#!/usr/bin/env bash
# Matrice de controle d'acces de bout en bout (vraies requetes HTTP, vraie session GLPI).
#
# Usage : access-matrix.sh <url GLPI> <none|reader|manager|admin> <login> <mot de passe> <json de setup.php>
#   none    : aucun droit sur le plugin -> chaque page refusee, aucune ecriture possible
#   reader  : lecture seule limitee a l'entite A -> pages lisibles, aucune ecriture, rien de B
#   manager : tous les droits du plugin, limite a A -> ecrit dans A, rien de B
#   admin   : super-admin -> tout reste accessible (detecte un controle trop strict)
#
# Une fonctionnalite ajoute ses controles dans tests/security/access-matrix.d/<issue>-<sujet>.sh
# (voir README.md) au lieu d'allonger ce script.
set -u
BASE=$1 ROLE=$2 LOGIN=$3 PASSWORD=$4 IDS=$5
JAR=$(mktemp)
FAIL=0
id() { jq -r ".$1" <<< "$IDS"; }
code() { curl -s -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" "$@"; }
body() { curl -s -c "$JAR" -b "$JAR" -H 'X-Requested-With: XMLHttpRequest' "$@"; }
page() { curl -s -c "$JAR" -b "$JAR" "$@"; }
csrf() { page "$BASE/front/central.php" | grep -o 'glpi:csrf_token" content="[^"]*"' | sed 's/.*content="//;s/"//'; }
expect() { # libelle, attendu, obtenu
  if [ "$2" = "$3" ]; then echo "OK  $1 ($3)"; else echo "::error::$ROLE - $1 : attendu $2, obtenu $3"; FAIL=1; fi
}
contains() { # libelle, texte, attendu (oui|non), contenu
  local got=non; grep -qF "$2" <<< "$4" && got=oui
  expect "$1" "$3" "$got"
}
tab() { # itemtype, onglet, id
  body "$BASE/ajax/common.tabs.php?_target=/plugins/finops/front/x.php&_itemtype=$1&_glpi_tab=$2&id=$3"
}
readable() { # pages consultables : refusees sans droit, ouvertes au lecteur, au gestionnaire et au super-admin
  local p
  for p in "$@"; do
    case $ROLE in
      none) expect "$p : sans droit" 403 "$(code "$BASE/plugins/finops/$p")" ;;
      *)    expect "$p : $ROLE" 200 "$(code "$BASE/plugins/finops/$p")" ;;
    esac
  done
}

for try in 1 2 3 4 5; do
  rm -f "$JAR"
  token=$(page "$BASE/" | grep -o 'name="_glpi_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
  curl -s -o /dev/null -c "$JAR" -b "$JAR" --data-urlencode "login_name=$LOGIN" \
    --data-urlencode "login_password=$PASSWORD" --data-urlencode "_glpi_csrf_token=$token" -d noAUTO=1 \
    "$BASE/front/login.php"
  [ "$(code "$BASE/front/central.php")" = 200 ] && break
  [ "$try" = 5 ] && { echo "::error::connexion impossible pour $LOGIN"; exit 1; }
  sleep 3
done

P=$BASE/plugins/finops/front
if [ "$ROLE" = admin ]; then SEE_B=oui; DENY=200; else SEE_B=non; DENY=403; fi

# Page d'accueil (Gestion > FinOps).
readable front/overview.php

# Page de configuration : droit de configuration de GLPI seulement (super-admin).
if [ "$ROLE" = admin ]; then
  expect "front/config.php : super-admin" 200 "$(code "$P/config.php")"
  expect "front/config.php : onglet des sources" 200 "$(code "$P/config.php?tab=sources")"
else
  expect "front/config.php : sans droit de configuration" 403 "$(code "$P/config.php")"
  expect "front/config.php : enregistrer sans droit de configuration" 403 \
    "$(code -d "update=1&default_currency=USD&max_upload_mb=9&_glpi_csrf_token=$(csrf)" "$P/config.php")"
fi
expect "front/config.php : enregistrer sans jeton CSRF" 403 \
  "$(code -d "update=1&default_currency=USD&max_upload_mb=9" "$P/config.php")"

# Onglet FinOps du profil : seulement avec le droit du plugin et le droit des profils du coeur.
profile_tab=$(tab Profile 'GlpiPlugin%5CFinops%5CProfile$0' 4)
[ "$ROLE" = admin ] && want=oui || want=non
contains "onglet FinOps d'un profil" "plugin_finops" $want "$profile_tab"

# Fragments : une fonctionnalite ajoute ses controles dans un NOUVEAU fichier
# tests/security/access-matrix.d/<issue>-<sujet>.sh, charge ici dans l'ordre des noms, avec les
# memes fonctions et variables (ROLE, P, id, code, body, page, csrf, expect, contains, tab, readable,
# SEE_B, DENY).
for fragment in "$(dirname "${BASH_SOURCE[0]}")"/access-matrix.d/*.sh; do
  [ -e "$fragment" ] || continue
  # shellcheck source=/dev/null
  . "$fragment"
done

rm -f "$JAR"
exit $FAIL
