# Аналіз результатів

```bash
pip install -r analysis/requirements.txt

# 1. Зведення прогонів bench/run.sh у таблиці (медіана повторів).
#    Також копіює summary.csv у застосунок для сторінки «Бенчмарки».
python3 analysis/parse.py bench/results/<дата>

# 2. Графіки (PNG 300 dpi + SVG) і таблиці Markdown для тексту диплому.
python3 analysis/plots.py                       # найновіші результати і найновіший leak-*
python3 analysis/plots.py analysis/results/<дата> --leak bench/results/leak-<дата> --percentile-vus 100
```

| Файл | Що містить |
|---|---|
| `results/<назва>/runs.csv` | кожен прогін: RPS, p50/p95/p99, помилки, середній CPU, пам'ять (середня і максимум) |
| `results/<назва>/summary.csv` | медіана повторів для режим × сценарій × VU |
| `figures/<назва>/rps-*.png` | RPS від кількості VU |
| `figures/<назва>/latency-p95-*.png`, `latency-p99-*.png` | затримки від VU |
| `figures/<назва>/memory-*.png`, `cpu-*.png` | ресурси контейнера |
| `figures/<назва>/peak-rps.png`, `gain-vs-fpm.png` | пік RPS і приріст відносно PHP-FPM |
| `figures/<назва>/percentiles-*-vusN.png` | p50/p95/p99 при фіксованій кількості VU |
| `figures/<назва>/leak-static.png`, `leak-memo.png` | пам'ять воркера в експерименті з витоками |
| `figures/<назва>/tables.md` | усі таблиці в Markdown |

CPU і пам'ять беруться з `*.prom.csv` (Prometheus), якщо моніторинг був увімкнений, інакше з `*.stats.csv`
(`docker stats`), лише за вимірювальну фазу прогону.

У git зберігаються лише зведені CSV і графіки, сирі результати k6 лишаються в `bench/results/` (в `.gitignore`).
