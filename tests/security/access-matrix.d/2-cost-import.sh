# Issue #2 : import des coûts, ressources et rattachement à un actif.
# Fragment de tests/security/access-matrix.sh (ROLE, P, id, code, body, page, csrf, expect, contains, tab,
# readable, SEE_B, DENY).

readable front/resource.php

# Coûts et ressources de B : jamais visibles hors super-admin.
if [ "$ROLE" != none ]; then
  contains "coûts : fournisseur de A" "finops-securite-a" oui "$(page "$P/overview.php")"
  contains "coûts : fournisseur de B" "finops-securite-b" $SEE_B "$(page "$P/overview.php")"
  contains "ressources : ressource de B" "securite-vm-b" $SEE_B "$(page "$P/resource.php")"
  expect "front/resource.php : ressource de A" 200 "$(code "$P/resource.php?id=$(id resource_A)")"
fi
[ "$ROLE" = none ] && expect "front/resource.php : ressource de A sans droit" 403 "$(code "$P/resource.php?id=$(id resource_A)")"
expect "front/resource.php : ressource de B" "$DENY" "$(code "$P/resource.php?id=$(id resource_B)")"

# Import : droit CREATE (gestionnaire et super-admin) ; sans jeton CSRF, toujours refusé.
case $ROLE in
  none|reader) want=403 ;;
  *) want=200 ;;
esac
expect "front/import.php : page d'import" $want "$(code "$P/import.php")"
csv=$(mktemp); bin=$(mktemp)
printf 'date,provider,account,service,resource,cost,currency\n2026-11-02,finops-securite-a,compte-a,Compute,securite-vm-a,1,EUR\n' > "$csv"
printf '\x89PNG\r\n\x1a\n\0\0\0\rIHDR' > "$bin"
upload() { # fichier, nom, entité, [jeton : oui|non]
  local token=(-F "_glpi_csrf_token=$(csrf)")
  [ "${4:-oui}" = non ] && token=()
  curl -s -w '\n%{http_code}' -c "$JAR" -b "$JAR" -F upload=1 -F "entities_id=$3" -F "cost_file=@$1;filename=$2" \
    "${token[@]}" "$P/import.php"
}
expect "import sans jeton CSRF" 403 "$(upload "$csv" c.csv "$(id a)" non | tail -1)"
case $ROLE in
  none|reader)
    expect "import d'un fichier de coûts" 403 "$(upload "$csv" c.csv "$(id a)" | tail -1)"
    ;;
  manager|admin)
    expect "import d'un fichier binaire déguisé en CSV" 302 "$(upload "$bin" couts.csv "$(id a)" | tail -1)"
    expect "import d'un fichier .php" 302 "$(upload "$csv" couts.php "$(id a)" | tail -1)"
    [ "$ROLE" = manager ] && expect "import dans l'entité B" 302 "$(upload "$csv" c.csv "$(id b)" | tail -1)"
    preview=$(upload "$csv" c.csv "$(id a)")
    expect "aperçu de l'import" 200 "$(tail -1 <<< "$preview")"
    contains "aperçu : bouton de confirmation" 'name="confirm"' oui "$preview"
    token=$(grep -o 'name="token" value="[a-f0-9]*"' <<< "$preview" | sed 's/.*value="//;s/"//')
    expect "confirmation de l'import" 302 "$(code -d "confirm=1&token=$token&_glpi_csrf_token=$(csrf)" "$P/import.php")"
    expect "confirmation avec un jeton déjà utilisé" 302 \
      "$(code -d "confirm=1&token=$token&_glpi_csrf_token=$(csrf)" "$P/import.php")"
    ;;
esac
rm -f "$csv" "$bin"

# Suppression d'un import : droit PURGE et entité de l'import.
[ "$ROLE" != admin ] && expect "supprimer l'import de B" 403 \
  "$(code -d "purge=1&id=$(id import_B)&_glpi_csrf_token=$(csrf)" "$P/import.php")"
case $ROLE in none|reader)
  expect "supprimer l'import de A" 403 "$(code -d "purge=1&id=$(id import_A)&_glpi_csrf_token=$(csrf)" "$P/import.php")" ;;
esac

# Rattachement : droit UPDATE et entité de la ressource.
case $ROLE in none|reader)
  expect "rattacher la ressource de A" 403 \
    "$(code -d "link=1&id=$(id resource_A)&itemtype=Computer&items_id=$(id computer_A)&_glpi_csrf_token=$(csrf)" \
      "$P/resource.php")" ;;
esac
[ "$ROLE" != admin ] && expect "rattacher la ressource de B" 403 \
  "$(code -d "link=1&id=$(id resource_B)&itemtype=Computer&items_id=$(id computer_B)&_glpi_csrf_token=$(csrf)" \
    "$P/resource.php")"
if [ "$ROLE" = manager ]; then
  # Ordinateur de B invisible : rattachement refusé, celui de A conservé.
  code -d "link=1&id=$(id resource_A)&itemtype=Computer&items_id=$(id computer_B)&_glpi_csrf_token=$(csrf)" \
    "$P/resource.php" > /dev/null
  contains "rattachement vers un ordinateur de B refusé" "VM FinOps securite A" oui \
    "$(page "$P/resource.php?id=$(id resource_A)")"
fi

# Onglet FinOps d'un ordinateur : droit du plugin et lecture de l'ordinateur.
tab_a=$(tab Computer 'GlpiPlugin%5CFinops%5CResource$1' "$(id computer_A)")
[ "$ROLE" = none ] && want=non || want=oui
contains "onglet FinOps de l'ordinateur de A" "securite-vm-a" $want "$tab_a"
contains "onglet FinOps de l'ordinateur de B" "securite-vm" $SEE_B \
  "$(tab Computer 'GlpiPlugin%5CFinops%5CResource$1' "$(id computer_B)")"
