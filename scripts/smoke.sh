#!/usr/bin/env bash
# Швидка перевірка запущених режимів: ping, вхід через API, CRUD-читання, вебсторінка,
# чотири сценарії навантаження і те, що тіла відповідей однакові в усіх режимах.
# Використання: ./scripts/smoke.sh [порт ...]   (типово 8081 8082 8083 8084)
set -u
PORTS=("$@")
[ ${#PORTS[@]} -eq 0 ] && PORTS=(8081 8082 8083 8084)
EMAIL=${SMOKE_EMAIL:-bench01@diploma.test}
PASSWORD=${SMOKE_PASSWORD:-password}
FAIL=0

# Шляхи, тіла відповідей яких мають бути однаковими в усіх режимах.
SAME_PATHS=(
    /api/ping
    /api/bench/db/orders/123
    "/api/bench/db/counterparties/7/orders?limit=10"
    /api/bench/cache/counterparties/7
    "/api/bench/compute/report?rows=5000&seed=42"
)

code() { curl -s --noproxy '*' -o /dev/null -w '%{http_code}' --max-time 20 "$@"; }
declare -A HASHES

for p in "${PORTS[@]}"; do
    base="http://localhost:$p"
    if [ "$(code "$base/api/ping")" != "200" ]; then
        echo "$p: недоступний"
        continue
    fi
    token=$(curl -s --noproxy '*' --max-time 10 -H 'Accept: application/json' -X POST "$base/api/auth/token" \
        -d "email=$EMAIL" -d "password=$PASSWORD" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
    auth=(-H "Authorization: Bearer $token" -H 'Accept: application/json')
    results="token=$([ -n "$token" ] && echo ok || echo FAIL)"
    [ -n "$token" ] || FAIL=1

    for path in /api/me /api/orders /api/counterparties/1 /api/documents /api/templates; do
        c=$(code "${auth[@]}" "$base$path")
        results="$results $path=$c"
        [ "$c" = "200" ] || FAIL=1
    done
    c=$(code -X POST "${auth[@]}" "$base/api/bench/cache/counterparties/7/hit")
    results="$results cache-hit=$c"; [ "$c" = "200" ] || FAIL=1
    c=$(code "$base/login"); results="$results /login=$c"; [ "$c" = "200" ] || FAIL=1

    for path in "${SAME_PATHS[@]}"; do
        body=$(curl -s --noproxy '*' --max-time 20 -w '\n%{http_code}' "${auth[@]}" "$base$path")
        status=${body##*$'\n'}
        [ "$status" = "200" ] || { FAIL=1; echo "  $p $path -> $status"; }
        HASHES["$p|$path"]="$(printf '%s' "${body%$'\n'*}" | md5sum | cut -c1-8)/${#body}b"
    done
    echo "$p: $results"
done

echo "Однаковість відповідей між режимами:"
for path in "${SAME_PATHS[@]}"; do
    line="  $path:"
    first=""
    for p in "${PORTS[@]}"; do
        h=${HASHES["$p|$path"]:-}
        [ -z "$h" ] && continue
        line="$line $p=$h"
        [ -z "$first" ] && first=$h
        [ "$h" = "$first" ] || FAIL=1
    done
    echo "$line"
done

[ $FAIL -eq 0 ] && echo "OK" || echo "Є помилки"
exit $FAIL
