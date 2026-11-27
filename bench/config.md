# Параметри стенду

Значення, з якими проводяться вимірювання. Після калібрування (див. нижче) оновіть цю таблицю,
і саме ці значення вказуються в тексті диплому. Фактичні параметри кожного прогону також
записуються в `bench/results/<дата>/meta.json`.

| Параметр | Значення | Де задається |
|---|---|---|
| CPU контейнера застосунку | 1.0 | `APP_CPUS` |
| Пам'ять контейнера застосунку | 512 MB | `APP_MEM` |
| PHP | 8.4 | `docker/php/Dockerfile` |
| PHP-FPM | `pm = static`, `pm.max_children = 8` | `docker/fpm/www.conf` |
| Octane workers | 2 | `OCTANE_WORKERS` |
| Octane max requests | 500 | `OCTANE_MAX_REQUESTS` |
| OPcache | увімкнено, `validate_timestamps=0` | `docker/php/php.ini` |
| PostgreSQL | 17, 1 CPU, 1 GB | `DB_CPUS`, `DB_MEM` |
| Redis | 7, 0.5 CPU, 256 MB | `REDIS_CPUS`, `REDIS_MEM` |
| k6 | 1.4.0, до 2 CPU | `K6_CPUS` |
| Дані | `SEED_SCALE=1`: 5 000 контрагентів, 100 000 замовлень, ~500 000 позицій | сидер |
| Тривалість вимірювання | 60 s + 5 s прогріву, 3 повтори, медіана | `bench/run.sh` |

## Калібрування кількості воркерів

Для чесного порівняння кожен режим має працювати з кількістю воркерів, найкращою для 1 vCPU.
Перед основним прогоном виконайте короткий прогін з різними значеннями і візьміть те, що дає
найбільший RPS на змішаному профілі без росту помилок:

```bash
for w in 1 2 4 8; do
  OCTANE_WORKERS=$w MODES="roadrunner swoole frankenphp" SCENARIOS=mixed VUS_LIST=100 REPEATS=1 DURATION=30s \
    STAMP=calib-workers-$w ./bench/run.sh
done
```

Для FPM змінюйте `pm.max_children` у `docker/fpm/www.conf` (4, 8, 16), перезбирайте образ і запускайте
`MODES=fpm` з тими самими параметрами.
