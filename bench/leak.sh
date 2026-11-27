#!/usr/bin/env bash
# Експеримент з витоками пам'яті (етап 6).
#
# Для кожного режиму і кожного варіанта (витік / виправлення) запускає застосунок з
# BENCH_LEAK_DEMO=true і ОДНИМ воркером Octane, відправляє запити пакетами і після кожного
# пакета записує пам'ять воркера (/api/_debug/memory) і контейнера (docker stats).
#
#   ./bench/leak.sh
#   MODES="swoole" VARIANTS="static fixed/static" REQUESTS=20000 ./bench/leak.sh
#   MAX_REQUESTS=500 ./bench/leak.sh       # показати, як --max-requests обмежує ріст
#
# Результат: bench/results/leak-<дата>/memory.csv і state.csv
set -euo pipefail
cd "$(dirname "$0")/.."

MODES=${MODES:-"fpm roadrunner swoole frankenphp"}
VARIANTS=${VARIANTS:-"static fixed/static memo fixed/memo"}
REQUESTS=${REQUESTS:-20000}
BATCH=${BATCH:-1000}
PARALLEL=${PARALLEL:-8}
MAX_REQUESTS=${MAX_REQUESTS:-1000000}   # «без обмеження» (0 у FrankenPHP означає перезапуск після кожного запиту)
STAMP=${STAMP:-$(date +%Y%m%d-%H%M%S)}
OUT="bench/results/leak-$STAMP"
declare -A PORT=([fpm]=8081 [roadrunner]=8082 [swoole]=8083 [frankenphp]=8084)

log() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*"; }
mkdir -p "$OUT"
echo "mode,variant,max_requests,requests,worker_pid,worker_mb,container_mb" > "$OUT/memory.csv"
echo "mode,requested,returned,leaked" > "$OUT/state.csv"

container_mb() {
    docker stats --no-stream --format '{{.MemUsage}}' "$1" | awk '{
        v = $1; n = v + 0; u = v; sub(/^[0-9.]+/, "", u);
        f = (u ~ /^G/) ? 1024 : (u ~ /^M/) ? 1 : (u ~ /^[kK]/) ? 1/1024 : 1/1048576; printf "%.1f", n * f }'
}

start() {
    local mode=$1
    docker compose --profile fpm --profile roadrunner --profile swoole --profile frankenphp \
        stop fpm roadrunner swoole frankenphp >/dev/null 2>&1 || true
    BENCH_LEAK_DEMO=true OCTANE_WORKERS=1 OCTANE_MAX_REQUESTS=$MAX_REQUESTS \
        docker compose --profile "$mode" up -d --force-recreate "$mode" >/dev/null 2>&1
    for _ in $(seq 1 60); do
        curl -s --noproxy '*' -o /dev/null "http://localhost:${PORT[$mode]}/api/ping" && return 0
        sleep 1
    done
    log "режим $mode не стартував"; exit 1
}

for mode in $MODES; do
    base="http://localhost:${PORT[$mode]}"

    # Витік стану: синглтон з Request.
    start "$mode"
    for name in alice bob carol; do
        got=$(curl -s --noproxy '*' "$base/api/leak/singleton?name=$name" | sed -n 's/.*"name":"\([^"]*\)".*/\1/p')
        echo "$mode,$name,$got,$([ "$got" = "$name" ] && echo no || echo yes)" >> "$OUT/state.csv"
    done
    log "$mode: стан синглтона -> $(tail -3 "$OUT/state.csv" | cut -d, -f3 | tr '\n' ' ')"

    for variant in $VARIANTS; do
        start "$mode"
        cid=$(docker compose --profile "$mode" ps -q "$mode")
        sent=0
        while [ "$sent" -le "$REQUESTS" ]; do
            mem=$(curl -s --noproxy '*' "$base/api/_debug/memory")
            pid=$(sed -n 's/.*"pid":\([0-9]*\).*/\1/p' <<< "$mem")
            wmb=$(sed -n 's/.*"memory_mb":\([0-9.]*\).*/\1/p' <<< "$mem")
            echo "$mode,$variant,$MAX_REQUESTS,$sent,$pid,$wmb,$(container_mb "$cid")" >> "$OUT/memory.csv"
            [ "$sent" -ge "$REQUESTS" ] && break
            # Унікальний key для memo, щоб кожен запит додавав новий запис.
            seq "$sent" $((sent + BATCH - 1)) | sed "s#.*#$base/api/leak/$variant?key=k&#" \
                | xargs -P "$PARALLEL" -n 50 curl -s --noproxy '*' -o /dev/null
            sent=$((sent + BATCH))
        done
        log "$mode $variant: $(tail -1 "$OUT/memory.csv")"
    done
done

docker compose --profile fpm --profile roadrunner --profile swoole --profile frankenphp \
    stop fpm roadrunner swoole frankenphp >/dev/null 2>&1 || true
log "Готово: $OUT"
