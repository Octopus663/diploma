#!/usr/bin/env python3
"""
Оцінка економічного ефекту: скільки інстансів (1 vCPU, 512 MB) потрібно для заданого трафіку
в кожному режимі і скільки це коштує.

    python3 analysis/economics.py [analysis/results/<назва>] [--p95 500] [--targets 1000 5000] [--scenario mixed]

Стабільна пропускна здатність режиму = найбільший RPS серед рівнів VU, на яких
p95 < порогу і частка помилок < 1%. Інстансів = ceil(цільовий RPS / (стабільний RPS × запас)).
Ціни: analysis/prices.json (перевірте актуальність!).
"""

import argparse
import json
import math
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import pandas as pd  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
MODES = ["fpm", "roadrunner", "swoole", "frankenphp"]
LABELS = {"fpm": "PHP-FPM", "roadrunner": "RoadRunner", "swoole": "Swoole", "frankenphp": "FrankenPHP"}
COLORS = {"fpm": "#6b7280", "roadrunner": "#2563eb", "swoole": "#16a34a", "frankenphp": "#9333ea"}


def stable_rps(df, p95_limit, error_limit=0.01):
    ok = df[(df["p95_ms"] < p95_limit) & (df["error_rate"] < error_limit)]
    return ok.groupby(["scenario", "mode"])["rps"].max()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("summary_dir", nargs="?", type=Path)
    parser.add_argument("--p95", type=float, default=500.0, help="поріг p95, мс")
    parser.add_argument("--targets", type=int, nargs="+", default=[1000, 5000])
    parser.add_argument("--scenario", default="mixed", help="сценарій для розрахунку вартості")
    parser.add_argument("--headroom", type=float, default=0.7, help="частка потужності, що використовується (запас)")
    args = parser.parse_args()

    if args.summary_dir:
        summary_dir = args.summary_dir.resolve()
    else:
        dirs = sorted((ROOT / "analysis" / "results").glob("*/summary.csv"), key=lambda p: p.stat().st_mtime)
        if not dirs:
            raise SystemExit("Немає analysis/results/*/summary.csv")
        summary_dir = dirs[-1].parent

    df = pd.read_csv(summary_dir / "summary.csv")
    prices = json.loads((ROOT / "analysis" / "prices.json").read_text())
    stable = stable_rps(df, args.p95)

    if args.scenario not in df["scenario"].unique():
        args.scenario = df["scenario"].unique()[0]

    lines = [
        "# Економічний ефект",
        "",
        f"Джерело: `{summary_dir.relative_to(ROOT)}/summary.csv`. Критерій стабільності: p95 < {args.p95:.0f} мс, "
        f"помилок < 1%. Запас потужності: використовується {args.headroom:.0%} стабільного RPS.",
        "",
        "## Стабільна пропускна здатність одного інстансу (1 vCPU, 512 MB), RPS",
        "",
    ]
    table = stable.unstack("mode").reindex(columns=[m for m in MODES if m in df["mode"].unique()])
    lines.append("| Сценарій | " + " | ".join(LABELS[m] for m in table.columns) + " |")
    lines.append("|---|" + "---|" * len(table.columns))
    for scenario, row in table.iterrows():
        lines.append(f"| {scenario} | " + " | ".join("—" if pd.isna(v) else f"{v:.0f}" for v in row) + " |")

    present = [m for m in MODES if m in df["mode"].unique()]
    measured = set(df[df["scenario"] == args.scenario]["mode"])
    no_data = [m for m in present if m not in measured]
    missing = [m for m in present if m in measured
               and (args.scenario not in table.index or pd.isna(table.loc[args.scenario, m]))]
    if no_data:
        lines += ["", f"Немає вимірювань сценарію `{args.scenario}`: " + ", ".join(LABELS[m] for m in no_data) + "."]
    if missing:
        lines += ["", f"Не досягають порогу у сценарії `{args.scenario}` навіть на найменшому рівні VU: "
                  + ", ".join(LABELS[m] for m in missing)
                  + ". Для них інстанси не рахуються; зменште навантаження (`VUS_LIST`) або підвищте `--p95`."]

    rows = []
    for target in args.targets:
        for mode in table.columns:
            rps = table.loc[args.scenario, mode] if args.scenario in table.index else float("nan")
            if pd.isna(rps) or rps <= 0:
                continue
            instances = math.ceil(target / (rps * args.headroom))
            for p in prices["providers"]:
                rows.append({"target_rps": target, "mode": mode, "stable_rps": round(rps, 1), "instances": instances,
                             "provider": p["name"], "monthly": round(instances * p["monthly"], 2)})
    cost = pd.DataFrame(rows)

    lines += ["", f"## Інстанси і вартість для сценарію `{args.scenario}`", "",
              f"Ціни: `analysis/prices.json`, валюта {prices['currency']}, перевірено: {prices['checked_at']}.", ""]
    for target in args.targets:
        lines += [f"### Цільовий трафік {target} RPS", "",
                  "| Режим | Стабільний RPS | Інстансів | " + " | ".join(p["name"] for p in prices["providers"]) + " | Економія відносно FPM |",
                  "|---|---|---|" + "---|" * len(prices["providers"]) + "---|"]
        c = cost[cost["target_rps"] == target]
        fpm_inst = c[c["mode"] == "fpm"]["instances"].max() if "fpm" in set(c["mode"]) else None
        for mode in [m for m in MODES if m in set(c["mode"])]:
            cm = c[c["mode"] == mode]
            inst = int(cm["instances"].iloc[0])
            saving = "—" if not fpm_inst or mode == "fpm" else f"{(1 - inst / fpm_inst) * 100:.0f}%"
            lines.append(f"| {LABELS[mode]} | {cm['stable_rps'].iloc[0]:.0f} | {inst} | "
                         + " | ".join(f"{v:.2f}" for v in cm["monthly"]) + f" | {saving} |")
        lines.append("")

    out_dir = ROOT / "analysis" / "figures" / summary_dir.name
    out_dir.mkdir(parents=True, exist_ok=True)
    if not cost.empty:
        fig, ax = plt.subplots(figsize=(7.5, 4.5))
        t = max(args.targets)
        c = cost[(cost["target_rps"] == t)].drop_duplicates("mode")
        ax.bar([LABELS[m] for m in c["mode"]], c["instances"], color=[COLORS[m] for m in c["mode"]])
        for i, v in enumerate(c["instances"]):
            ax.annotate(str(v), (i, v), ha="center", va="bottom")
        ax.set_ylabel("Інстансів 1 vCPU / 512 MB")
        ax.set_title(f"Кількість інстансів для {t} RPS (сценарій {args.scenario})")
        ax.grid(axis="y", alpha=0.3)
        fig.tight_layout()
        fig.savefig(out_dir / "economics-instances.png", dpi=300)
        fig.savefig(out_dir / "economics-instances.svg")
        plt.close(fig)

    (out_dir / "economics.md").write_text("\n".join(lines), encoding="utf-8")
    cost.to_csv(summary_dir / "economics.csv", index=False)
    print("\n".join(lines))


if __name__ == "__main__":
    main()
