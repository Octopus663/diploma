#!/usr/bin/env python3
"""
Зведення результатів bench/run.sh у таблиці.

    python3 analysis/parse.py bench/results/<дата> [bench/results/<дата2> ...] [--no-publish]

Створює analysis/results/<назва>/:
    runs.csv     - один рядок на прогін (режим, сценарій, VU, повтор, RPS, затримки, CPU, RAM)
    summary.csv  - медіана повторів для кожної комбінації режим × сценарій × VU
і копіює summary.csv у app/resources/benchmarks/summary.csv для сторінки «Бенчмарки» (якщо не --no-publish).
"""

import argparse
import csv
import json
import re
import shutil
import statistics
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RUN_RE = re.compile(r"vus-(\d+)-run-(\d+)\.json$")
MODES = ["fpm", "roadrunner", "swoole", "frankenphp"]
SUMMARY_FIELDS = ["mode", "scenario", "vus", "rps", "p50_ms", "p95_ms", "p99_ms", "error_rate", "cpu_pct", "mem_mb", "mem_max_mb", "runs"]


def read_series(path: Path):
    """CSV з колонками ts, cpu_pct, mem_mb -> список (ts, cpu, mem)."""
    rows = []
    with path.open() as f:
        for r in csv.DictReader(f):
            try:
                rows.append((int(float(r["ts"])), float(r["cpu_pct"]), float(r["mem_mb"])))
            except (ValueError, KeyError):
                continue
    return rows


def resource_stats(json_path: Path, window):
    """Середній CPU, середня і максимальна пам'ять за вимірювальну фазу."""
    base = json_path.with_suffix("")
    for suffix in (".prom.csv", ".stats.csv"):
        p = base.with_name(base.name + suffix)
        if p.exists():
            rows = read_series(p)
            if window:
                start, end = window
                rows = [r for r in rows if start <= r[0] <= end] or rows
            rows = [r for r in rows if r[1] == r[1]]  # без NaN
            if rows:
                return (
                    statistics.mean(r[1] for r in rows),
                    statistics.mean(r[2] for r in rows),
                    max(r[2] for r in rows),
                    suffix.strip(".").split(".")[0],
                )
    return None, None, None, None


def load_windows(results: Path):
    """Межі вимірювальної фази з runs.csv (початок + прогрів .. кінець)."""
    windows = {}
    meta = {}
    meta_file = results / "meta.json"
    if meta_file.exists():
        meta = json.loads(meta_file.read_text())
    warm = meta.get("run_warmup", "5s")
    warm_s = int(re.sub(r"\D", "", warm) or 0) + 1
    runs_file = results / "runs.csv"
    if runs_file.exists():
        with runs_file.open() as f:
            for r in csv.DictReader(f):
                windows[r["base"]] = (int(r["start"]) + warm_s, int(r["end"]))
    return windows


def parse_results(results: Path):
    windows = load_windows(results)
    rows = []
    for path in sorted(results.glob("*/*/vus-*-run-*.json")):
        m = RUN_RE.search(path.name)
        if not m:
            continue
        mode, scenario = path.parent.parent.name, path.parent.name
        data = json.loads(path.read_text())
        d = data.get("diploma", {})
        lat = d.get("latency_ms", {})
        rel = str(path.relative_to(results).with_suffix(""))
        cpu, mem, mem_max, source = resource_stats(path, windows.get(rel))
        rows.append({
            "source": results.name,
            "mode": mode,
            "scenario": scenario,
            "vus": int(m.group(1)),
            "run": int(m.group(2)),
            "requests": d.get("requests", 0),
            "rps": round(d.get("rps", 0.0), 2),
            "p50_ms": round(lat.get("med", 0.0), 2),
            "p95_ms": round(lat.get("p(95)", 0.0), 2),
            "p99_ms": round(lat.get("p(99)", 0.0), 2),
            "avg_ms": round(lat.get("avg", 0.0), 2),
            "error_rate": round(d.get("error_rate", 0.0), 5),
            "cpu_pct": round(cpu, 1) if cpu is not None else "",
            "mem_mb": round(mem, 1) if mem is not None else "",
            "mem_max_mb": round(mem_max, 1) if mem_max is not None else "",
            "resource_source": source or "",
        })
    return rows


def summarize(rows):
    groups = {}
    for r in rows:
        groups.setdefault((r["mode"], r["scenario"], r["vus"]), []).append(r)

    def med(items, key):
        values = [float(i[key]) for i in items if i[key] != ""]
        return round(statistics.median(values), 2) if values else ""

    out = []
    order = {m: i for i, m in enumerate(MODES)}
    for (mode, scenario, vus), items in sorted(groups.items(), key=lambda kv: (kv[0][1], order.get(kv[0][0], 99), kv[0][2])):
        out.append({
            "mode": mode, "scenario": scenario, "vus": vus,
            **{k: med(items, k) for k in ["rps", "p50_ms", "p95_ms", "p99_ms", "error_rate", "cpu_pct", "mem_mb", "mem_max_mb"]},
            "runs": len(items),
        })
    return out


def write_csv(path: Path, rows, fields=None):
    path.parent.mkdir(parents=True, exist_ok=True)
    fields = fields or list(rows[0].keys())
    with path.open("w", newline="") as f:
        w = csv.DictWriter(f, fieldnames=fields, extrasaction="ignore", lineterminator="\n")
        w.writeheader()
        w.writerows(rows)


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("results", nargs="+", type=Path)
    parser.add_argument("--name", help="назва набору (типово ім'я першої теки результатів)")
    parser.add_argument("--no-publish", action="store_true", help="не копіювати summary.csv у застосунок")
    args = parser.parse_args()

    rows = []
    for results in args.results:
        rows += parse_results(results)
    if not rows:
        raise SystemExit("Не знайдено жодного прогону (*/*/vus-*-run-*.json)")

    name = args.name or args.results[0].name
    out = ROOT / "analysis" / "results" / name
    summary = summarize(rows)
    write_csv(out / "runs.csv", rows)
    write_csv(out / "summary.csv", summary, SUMMARY_FIELDS)
    print(f"Прогонів: {len(rows)}, комбінацій: {len(summary)} -> {out}")

    if not args.no_publish:
        target = ROOT / "app" / "resources" / "benchmarks" / "summary.csv"
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(out / "summary.csv", target)
        print(f"Опубліковано для сторінки «Бенчмарки»: {target.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
