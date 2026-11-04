#!/usr/bin/env bash
# Збір CPU і пам'яті контейнера раз на секунду (docker stats) у CSV.
# Використання: lib/stats.sh <container> <out.csv>   (зупиняється сигналом TERM)
set -u
container=$1
out=$2
echo "ts,cpu_pct,mem_mb,mem_limit_mb" > "$out"

to_mb() {
    # "123.4MiB" / "1.2GiB" / "512kB" -> МБ
    awk -v v="$1" 'BEGIN {
        n = v + 0; u = v; sub(/^[0-9.]+/, "", u);
        f = (u ~ /^G/) ? 1024 : (u ~ /^M/) ? 1 : (u ~ /^[kK]/) ? 1/1024 : 1/1048576;
        printf "%.1f", n * f }'
}

trap 'exit 0' TERM INT
while true; do
    line=$(docker stats --no-stream --format '{{.CPUPerc}};{{.MemUsage}}' "$container" 2>/dev/null) || { sleep 1; continue; }
    cpu=${line%%;*}; cpu=${cpu%\%}
    mem=${line#*;}; used=${mem%% / *}; limit=${mem##* / }
    echo "$(date +%s),$cpu,$(to_mb "$used"),$(to_mb "$limit")" >> "$out"
done
