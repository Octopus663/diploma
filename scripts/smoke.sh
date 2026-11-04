#!/usr/bin/env bash
# Швидка перевірка всіх запущених режимів: ping, вхід через API, CRUD-читання, вебсторінка.
# Використання: ./scripts/smoke.sh [порт ...]   (типово 8081 8082 8083 8084)
set -u
PORTS=("$@")
[ ${#PORTS[@]} -eq 0 ] && PORTS=(8081 8082 8083 8084)
EMAIL=${SMOKE_EMAIL:-bench01@diploma.test}
PASSWORD=${SMOKE_PASSWORD:-password}
FAIL=0

code() { curl -s --noproxy '*' -o /dev/null -w '%{http_code}' --max-time 10 "$@"; }

for p in "${PORTS[@]}"; do
    base="http://localhost:$p"
    if [ "$(code "$base/api/ping")" != "200" ]; then
        echo "$p: недоступний"
        continue
    fi
    token=$(curl -s --noproxy '*' --max-time 10 -H 'Accept: application/json' -X POST "$base/api/auth/token" \
        -d "email=$EMAIL" -d "password=$PASSWORD" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
    auth=(-H "Authorization: Bearer $token" -H 'Accept: application/json')
    results="ping=200 token=$([ -n "$token" ] && echo ok || echo FAIL)"
    for path in /api/me /api/orders /api/orders/1 /api/counterparties/1 /api/documents /api/templates; do
        c=$(code "${auth[@]}" "$base$path")
        results="$results $path=$c"
        [ "$c" = "200" ] || FAIL=1
    done
    c=$(code "$base/login"); results="$results /login=$c"; [ "$c" = "200" ] || FAIL=1
    echo "$p: $results"
done

exit $FAIL
