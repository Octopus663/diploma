#!/usr/bin/env python3
"""
Експорт CPU і пам'яті контейнера застосунку з Prometheus за час кожного прогону.

Використання: python3 bench/export_metrics.py bench/results/<дата> [--prometheus http://localhost:9090]

Читає <results>/runs.csv (його пише bench/run.sh) і для кожного прогону створює
<base>.prom.csv з колонками ts,cpu_pct,mem_mb. Це точніший ряд, ніж *.stats.csv з docker stats,
бо Prometheus знімає метрики рівно щосекунди.
"""

import argparse
import csv
import json
import sys
import urllib.parse
import urllib.request
from pathlib import Path

QUERIES = {
    "cpu_pct": 'container_cpu_percent{{service="{service}"}}',
    "mem_mb": 'container_memory_bytes{{service="{service}"}} / 1048576',
}


def query_range(prom, expr, start, end):
    params = urllib.parse.urlencode({"query": expr, "start": start, "end": end, "step": "1"})
    req = urllib.request.Request(f"{prom}/api/v1/query_range?{params}")
    with urllib.request.urlopen(req, timeout=30) as resp:
        data = json.load(resp)
    result = data.get("data", {}).get("result", [])
    if not result:
        return {}
    return {int(float(ts)): float(v) for ts, v in result[0]["values"]}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("results")
    parser.add_argument("--prometheus", default="http://localhost:9090")
    args = parser.parse_args()

    results = Path(args.results)
    runs_file = results / "runs.csv"
    if not runs_file.exists():
        sys.exit(f"Немає {runs_file}")

    # Без проксі: Prometheus локальний.
    urllib.request.install_opener(urllib.request.build_opener(urllib.request.ProxyHandler({})))

    count = 0
    with runs_file.open() as f:
        for row in csv.DictReader(f):
            series = {
                key: query_range(args.prometheus, expr.format(service=row["mode"]), row["start"], row["end"])
                for key, expr in QUERIES.items()
            }
            stamps = sorted(set().union(*[s.keys() for s in series.values()]))
            if not stamps:
                print(f"немає даних: {row['base']}")
                continue
            out = results / f"{row['base']}.prom.csv"
            with out.open("w", newline="") as o:
                w = csv.writer(o)
                w.writerow(["ts", "cpu_pct", "mem_mb"])
                for ts in stamps:
                    w.writerow([ts] + [round(series[k].get(ts, float("nan")), 2) for k in QUERIES])
            count += 1

    print(f"Експортовано прогонів: {count}")


if __name__ == "__main__":
    main()
