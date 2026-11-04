#!/usr/bin/env bash
# Повний експеримент: режими × сценарії × кількість VU × повтори.
#
# Приклади:
#   ./bench/run.sh                                   # повний прогін (довго, див. оцінку часу на старті)
#   QUICK=1 ./bench/run.sh                           # швидкий прогін ~40 хв
#   MODES="fpm swoole" SCENARIOS="ping" VUS_LIST="10 100" REPEATS=1 DURATION=20s ./bench/run.sh
#   SOAK=1 MODES="swoole" ./bench/run.sh             # лише тривалий тест пам'яті (SOAK_DURATION, типово 30m)
#
# Перед першим запуском: зібрати образи, створити дані (див. README).
# Результати: bench/results/<дата>/<режим>/<сценарій>/vus-<N>-run-<R>.json і .stats.csv
set -euo pipefail

cd "$(dirname "$0")/.."

if [ "${QUICK:-0}" = "1" ]; then
    : "${VUS_LIST:=10 100 300}" "${REPEATS:=1}" "${DURATION:=30s}" "${WARMUP_TIME:=20s}"
fi

MODES=${MODES:-"fpm roadrunner swoole frankenphp"}
SCENARIOS=${SCENARIOS:-"ping auth database cache compute mixed"}
VUS_LIST=${VUS_LIST:-"10 50 100 200 300 500"}
REPEATS=${REPEATS:-3}
DURATION=${DURATION:-60s}
RUN_WARMUP=${RUN_WARMUP:-5s}              # короткий прогрів на початку кожного прогону
WARMUP_TIME=${WARMUP_TIME:-30s}           # прогрів після (пере)запуску контейнера
PAUSE=${PAUSE:-5}                         # пауза між прогонами, с
RESTART_PER=${RESTART_PER:-scenario}      # scenario | run | mode: коли перезапускати контейнер
SOAK=${SOAK:-0}
SOAK_DURATION=${SOAK_DURATION:-30m}
SOAK_RATE=${SOAK_RATE:-50}
STAMP=${STAMP:-$(date +%Y%m%d-%H%M%S)}
RESULTS="bench/results/$STAMP"
COMPOSE=(docker compose)

declare -A PORT=([fpm]=8081 [roadrunner]=8082 [swoole]=8083 [frankenphp]=8084)

log() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*"; }

seconds() {
    local v=$1
    case $v in
        *ms) echo 0 ;;
        *m) echo $(( ${v%m} * 60 )) ;;
        *h) echo $(( ${v%h} * 3600 )) ;;
        *s) echo "${v%s}" ;;
        *) echo "$v" ;;
    esac
}

stop_apps() {
    "${COMPOSE[@]}" --profile fpm --profile roadrunner --profile swoole --profile frankenphp \
        stop fpm roadrunner swoole frankenphp >/dev/null 2>&1 || true
}

start_app() {
    local mode=$1
    stop_apps
    "${COMPOSE[@]}" --profile "$mode" up -d --force-recreate "$mode" >/dev/null
    for _ in $(seq 1 60); do
        if curl -s --noproxy '*' -o /dev/null -w '%{http_code}' "http://localhost:${PORT[$mode]}/api/ping" | grep -q 200; then
            return 0
        fi
        sleep 1
    done
    log "ПОМИЛКА: режим $mode не відповідає"
    "${COMPOSE[@]}" --profile "$mode" logs --tail 50 "$mode"
    exit 1
}

container_of() {
    "${COMPOSE[@]}" --profile "$1" ps -q "$1"
}

k6_run() {
    # k6_run <script> <out-json> [env...]
    local script=$1 out=$2
    shift 2
    local envs=()
    for e in "$@"; do envs+=(-e "$e"); done
    "${COMPOSE[@]}" --profile bench run --rm -T \
        -e TOKENS=/results/.tokens.json -e OUT="/results/${out#bench/results/}" "${envs[@]}" \
        k6 run --quiet "$script"
}

warmup() {
    local mode=$1
    [ "$(seconds "$WARMUP_TIME")" -gt 0 ] || return 0
    mkdir -p "$RESULTS/$mode"
    log "  прогрів $WARMUP_TIME"
    k6_run mixed.js "$RESULTS/$mode/warmup.json" TARGET="http://$mode:8000" VUS=20 DURATION="$WARMUP_TIME" WARMUP=0s LABEL=warmup >/dev/null
}

measure() {
    # measure <mode> <scenario> <vus> <run>
    local mode=$1 scenario=$2 vus=$3 run=$4
    local dir="$RESULTS/$mode/$scenario"
    local base="$dir/vus-$vus-run-$run"
    mkdir -p "$dir"
    bench/lib/stats.sh "$(container_of "$mode")" "$base.stats.csv" &
    local stats_pid=$!
    k6_run "$scenario.js" "$base.json" TARGET="http://$mode:8000" VUS="$vus" DURATION="$DURATION" \
        WARMUP="$RUN_WARMUP" LABEL="$mode/$scenario" || log "  k6 завершився з помилкою (пороги), результат збережено"
    kill "$stats_pid" 2>/dev/null || true
    wait "$stats_pid" 2>/dev/null || true
}

# ---------- підготовка ----------
mkdir -p "$RESULTS"
log "Результати: $RESULTS"

"${COMPOSE[@]}" up -d db redis >/dev/null 2>&1
"${COMPOSE[@]}" run --rm -T app php artisan migrate --force >/dev/null 2>&1
if [ "$("${COMPOSE[@]}" exec -T db psql -U "${DB_USERNAME:-diploma}" -d "${DB_DATABASE:-diploma}" -tAc 'select count(*) from orders')" -eq 0 ]; then
    log "БД порожня, запускаю сидер"
    "${COMPOSE[@]}" run --rm -T app php artisan db:seed --force
fi
"${COMPOSE[@]}" run --rm -T app php artisan bench:tokens 2>/dev/null > bench/results/.tokens.json \
    || { log "ПОМИЛКА: не вдалося видати токени (bench:tokens)"; exit 1; }
"${COMPOSE[@]}" run --rm -T app php artisan bench:warm-cache --flush >/dev/null

cat > "$RESULTS/meta.json" <<JSON
{
  "stamp": "$STAMP",
  "git": "$(git rev-parse --short HEAD 2>/dev/null || echo unknown)",
  "modes": "$MODES",
  "scenarios": "$SCENARIOS",
  "vus": "$VUS_LIST",
  "repeats": $REPEATS,
  "duration": "$DURATION",
  "run_warmup": "$RUN_WARMUP",
  "warmup_time": "$WARMUP_TIME",
  "restart_per": "$RESTART_PER",
  "app_cpus": "${APP_CPUS:-1.0}",
  "app_mem": "${APP_MEM:-512m}",
  "octane_workers": "${OCTANE_WORKERS:-2}",
  "octane_max_requests": "${OCTANE_MAX_REQUESTS:-500}",
  "host_cpus": "$(docker info --format '{{.NCPU}}')",
  "host_mem_bytes": "$(docker info --format '{{.MemTotal}}')",
  "docker": "$(docker version --format '{{.Server.Version}}')",
  "os": "$(docker info --format '{{.OperatingSystem}}')"
}
JSON

# ---------- тривалий тест пам'яті ----------
if [ "$SOAK" = "1" ]; then
    for mode in $MODES; do
        log "Soak: $mode, $SOAK_DURATION при $SOAK_RATE запитів/с"
        start_app "$mode"
        mkdir -p "$RESULTS/$mode/soak"
        bench/lib/stats.sh "$(container_of "$mode")" "$RESULTS/$mode/soak/soak.stats.csv" &
        stats_pid=$!
        k6_run soak.js "$RESULTS/$mode/soak/soak.json" TARGET="http://$mode:8000" RATE="$SOAK_RATE" DURATION="$SOAK_DURATION" LABEL="$mode/soak" || true
        kill "$stats_pid" 2>/dev/null || true
    done
    stop_apps
    log "Готово"
    exit 0
fi

# ---------- оцінка часу ----------
n_modes=$(wc -w <<< "$MODES"); n_sc=$(wc -w <<< "$SCENARIOS"); n_vus=$(wc -w <<< "$VUS_LIST")
per_run=$(( $(seconds "$DURATION") + $(seconds "$RUN_WARMUP") + PAUSE + 10 ))
total=$(( n_modes * n_sc * n_vus * REPEATS * per_run + n_modes * n_sc * ($(seconds "$WARMUP_TIME") + 15) ))
log "Прогонів: $(( n_modes * n_sc * n_vus * REPEATS )), орієнтовно $(( total / 60 )) хв"

# ---------- основний цикл ----------
for mode in $MODES; do
    log "=== Режим $mode ==="
    [ "$RESTART_PER" = "mode" ] && { start_app "$mode"; warmup "$mode"; }
    for scenario in $SCENARIOS; do
        [ "$RESTART_PER" = "scenario" ] && { start_app "$mode"; warmup "$mode"; }
        for vus in $VUS_LIST; do
            for run in $(seq 1 "$REPEATS"); do
                [ "$RESTART_PER" = "run" ] && { start_app "$mode"; warmup "$mode"; }
                log "  $scenario VUS=$vus run=$run"
                measure "$mode" "$scenario" "$vus" "$run"
                sleep "$PAUSE"
            done
        done
    done
done

stop_apps
log "Готово: $RESULTS"
log "Далі: python3 analysis/parse.py $RESULTS && python3 analysis/plots.py"
