#!/usr/bin/env python3
"""
Графіки і таблиці для тексту диплому.

    python3 analysis/plots.py [analysis/results/<назва>] [--leak bench/results/leak-<дата>]

Без аргументів бере найновіший набір в analysis/results/ і найновіший bench/results/leak-*.
Результат: analysis/figures/<назва>/*.png (300 dpi) і *.svg, analysis/figures/<назва>/tables.md.
"""

import argparse
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import pandas as pd  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
MODES = ["fpm", "roadrunner", "swoole", "frankenphp"]
LABELS = {"fpm": "PHP-FPM", "roadrunner": "RoadRunner", "swoole": "Swoole", "frankenphp": "FrankenPHP"}
COLORS = {"fpm": "#6b7280", "roadrunner": "#2563eb", "swoole": "#16a34a", "frankenphp": "#9333ea"}
MARKERS = {"fpm": "o", "roadrunner": "s", "swoole": "^", "frankenphp": "D"}
SCENARIO_TITLES = {
    "ping": "Сценарій 1: легкий JSON",
    "auth": "Сценарій 1а: JSON з автентифікацією",
    "database": "Сценарій 2: реляційна БД (Eloquent)",
    "cache": "Сценарій 3: кеш Redis",
    "compute": "Сценарій 4: обчислення і серіалізація",
    "mixed": "Змішаний профіль",
}

plt.rcParams.update({
    "figure.figsize": (7.5, 4.5),
    "axes.grid": True,
    "grid.alpha": 0.3,
    "axes.spines.top": False,
    "axes.spines.right": False,
    "font.size": 10,
})


def save(fig, out: Path, name: str):
    out.mkdir(parents=True, exist_ok=True)
    fig.tight_layout()
    fig.savefig(out / f"{name}.png", dpi=300)
    fig.savefig(out / f"{name}.svg")
    plt.close(fig)


def modes_in(df):
    return [m for m in MODES if m in set(df["mode"])]


def line_by_vus(df, scenario, metric, ylabel, out, name, title_suffix=""):
    data = df[df["scenario"] == scenario]
    if data.empty or data[metric].isna().all():
        return
    fig, ax = plt.subplots()
    for mode in modes_in(data):
        d = data[data["mode"] == mode].sort_values("vus")
        ax.plot(d["vus"], d[metric], marker=MARKERS[mode], color=COLORS[mode], label=LABELS[mode], linewidth=2)
    ax.set_xlabel("Кількість одночасних користувачів (VU)")
    ax.set_ylabel(ylabel)
    ax.set_title(SCENARIO_TITLES.get(scenario, scenario) + title_suffix)
    ax.set_ylim(bottom=0)
    ax.legend()
    save(fig, out, name)


def peak_bars(df, out):
    peak = df.groupby(["scenario", "mode"])["rps"].max().unstack("mode")
    peak = peak[[m for m in MODES if m in peak.columns]]
    order = [s for s in SCENARIO_TITLES if s in peak.index] + [s for s in peak.index if s not in SCENARIO_TITLES]
    peak = peak.loc[order]
    fig, ax = plt.subplots(figsize=(9, 4.8))
    width = 0.8 / len(peak.columns)
    for i, mode in enumerate(peak.columns):
        xs = [x + i * width for x in range(len(peak.index))]
        ax.bar(xs, peak[mode], width=width, color=COLORS[mode], label=LABELS[mode])
    ax.set_xticks([x + width * (len(peak.columns) - 1) / 2 for x in range(len(peak.index))])
    ax.set_xticklabels(peak.index)
    ax.set_ylabel("Пікова пропускна здатність, RPS")
    ax.set_title("Максимальний RPS за сценаріями")
    ax.legend()
    save(fig, out, "peak-rps")
    return peak


def gain_bars(peak, out):
    if "fpm" not in peak.columns:
        return None
    gain = (peak.div(peak["fpm"], axis=0) - 1) * 100
    gain = gain.drop(columns=["fpm"])
    fig, ax = plt.subplots(figsize=(9, 4.8))
    width = 0.8 / max(1, len(gain.columns))
    for i, mode in enumerate(gain.columns):
        xs = [x + i * width for x in range(len(gain.index))]
        bars = ax.bar(xs, gain[mode], width=width, color=COLORS[mode], label=LABELS[mode])
        for b, v in zip(bars, gain[mode]):
            ax.annotate(f"{v:+.0f}%", (b.get_x() + b.get_width() / 2, v), ha="center",
                        va="bottom" if v >= 0 else "top", fontsize=7)
    ax.axhline(0, color="black", linewidth=0.8)
    ax.set_xticks([x + width * (len(gain.columns) - 1) / 2 for x in range(len(gain.index))])
    ax.set_xticklabels(gain.index)
    ax.set_ylabel("Приріст пікового RPS відносно PHP-FPM, %")
    ax.set_title("Приріст продуктивності відносно PHP-FPM")
    ax.legend()
    save(fig, out, "gain-vs-fpm")
    return gain


def percentiles_at(df, vus, out):
    data = df[df["vus"] == vus]
    if data.empty:
        return
    for scenario in data["scenario"].unique():
        d = data[data["scenario"] == scenario].set_index("mode")
        d = d.loc[[m for m in MODES if m in d.index]]
        fig, ax = plt.subplots()
        width = 0.25
        for i, (col, label) in enumerate([("p50_ms", "p50"), ("p95_ms", "p95"), ("p99_ms", "p99")]):
            ax.bar([x + i * width for x in range(len(d))], d[col], width=width, label=label, alpha=0.6 + 0.15 * i,
                   color=[COLORS[m] for m in d.index])
        ax.set_xticks([x + width for x in range(len(d))])
        ax.set_xticklabels([LABELS[m] for m in d.index])
        ax.set_ylabel("Затримка, мс")
        ax.set_title(f"{SCENARIO_TITLES.get(scenario, scenario)}: перцентилі затримки при {vus} VU")
        ax.legend(["p50", "p95", "p99"])
        save(fig, out, f"percentiles-{scenario}-vus{vus}")


def leak_plots(leak_dir: Path, out: Path):
    mem_file = leak_dir / "memory.csv"
    if not mem_file.exists():
        return None
    df = pd.read_csv(mem_file)
    for variant in ["static", "memo"]:
        fig, ax = plt.subplots()
        for mode in modes_in(df):
            for v, style in [(variant, "-"), (f"fixed/{variant}", "--")]:
                d = df[(df["mode"] == mode) & (df["variant"] == v)].sort_values("requests")
                if d.empty:
                    continue
                label = f"{LABELS[mode]}{' (виправлено)' if v.startswith('fixed') else ''}"
                ax.plot(d["requests"], d["worker_mb"], style, color=COLORS[mode], marker=MARKERS[mode],
                        markersize=3, label=label)
        ax.set_xlabel("Оброблено запитів")
        ax.set_ylabel("Пам'ять воркера, МБ")
        title = {"static": "Статичний масив", "memo": "Необмежена мемоізація в синглтоні"}[variant]
        ax.set_title(f"Витік пам'яті: {title}")
        ax.legend(fontsize=8, ncol=2)
        save(fig, out, f"leak-{variant}")
    return df


def markdown_table(df: pd.DataFrame, floatfmt=".1f"):
    cols = list(df.columns)
    lines = ["| " + " | ".join(str(c) for c in cols) + " |", "|" + "---|" * len(cols)]
    for _, row in df.iterrows():
        cells = []
        for c in cols:
            v = row[c]
            cells.append(format(v, floatfmt) if isinstance(v, float) else str(v))
        lines.append("| " + " | ".join(cells) + " |")
    return "\n".join(lines)


def latest(path: Path, pattern: str):
    items = sorted(path.glob(pattern), key=lambda p: p.stat().st_mtime)
    return items[-1] if items else None


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("summary_dir", nargs="?", type=Path)
    parser.add_argument("--leak", type=Path)
    parser.add_argument("--percentile-vus", type=int, default=None, help="VU для графіків перцентилів")
    args = parser.parse_args()

    summary_dir = (args.summary_dir.resolve() if args.summary_dir else latest(ROOT / "analysis" / "results", "*"))
    if not summary_dir or not (summary_dir / "summary.csv").exists():
        raise SystemExit("Немає analysis/results/*/summary.csv. Спершу запустіть analysis/parse.py")
    df = pd.read_csv(summary_dir / "summary.csv")
    out = ROOT / "analysis" / "figures" / summary_dir.name

    for scenario in df["scenario"].unique():
        line_by_vus(df, scenario, "rps", "Запитів за секунду (RPS)", out, f"rps-{scenario}")
        line_by_vus(df, scenario, "p95_ms", "Затримка p95, мс", out, f"latency-p95-{scenario}")
        line_by_vus(df, scenario, "p99_ms", "Затримка p99, мс", out, f"latency-p99-{scenario}")
        line_by_vus(df, scenario, "mem_mb", "Пам'ять контейнера, МБ", out, f"memory-{scenario}")
        line_by_vus(df, scenario, "cpu_pct", "CPU, % одного ядра", out, f"cpu-{scenario}")

    peak = peak_bars(df, out)
    gain = gain_bars(peak, out)
    vus = args.percentile_vus or int(sorted(df["vus"].unique())[len(df["vus"].unique()) // 2])
    percentiles_at(df, vus, out)

    tables = ["# Таблиці результатів", "", f"Джерело: `{summary_dir.relative_to(ROOT)}/summary.csv`", ""]
    tables += ["## Пікова пропускна здатність, RPS", "", markdown_table(peak.rename(columns=LABELS).reset_index()), ""]
    if gain is not None:
        tables += ["## Приріст відносно PHP-FPM, %", "", markdown_table(gain.rename(columns=LABELS).reset_index(), "+.1f"), ""]
    for scenario in df["scenario"].unique():
        d = df[df["scenario"] == scenario].copy()
        d["mode"] = d["mode"].map(LABELS)
        d = d[["mode", "vus", "rps", "p50_ms", "p95_ms", "p99_ms", "error_rate", "cpu_pct", "mem_mb"]]
        tables += [f"## {SCENARIO_TITLES.get(scenario, scenario)}", "", markdown_table(d), ""]

    leak_dir = args.leak.resolve() if args.leak else latest(ROOT / "bench" / "results", "leak-*")
    if leak_dir and leak_plots(leak_dir, out) is not None:
        ldf = pd.read_csv(leak_dir / "memory.csv")
        first = ldf.groupby(["mode", "variant"])["worker_mb"].first()
        last = ldf.groupby(["mode", "variant"])["worker_mb"].last()
        reqs = ldf.groupby(["mode", "variant"])["requests"].max()
        t = pd.DataFrame({"start_mb": first, "end_mb": last, "requests": reqs}).reset_index()
        tables += [f"## Пам'ять воркера, `{leak_dir.relative_to(ROOT)}`", "", markdown_table(t), ""]

    out.mkdir(parents=True, exist_ok=True)
    (out / "tables.md").write_text("\n".join(tables), encoding="utf-8")
    print(f"Графіки і таблиці: {out.relative_to(ROOT)} ({len(list(out.glob('*.png')))} PNG)")


if __name__ == "__main__":
    main()
