# Diploma: Laravel Octane benchmark

Вебсистема обліку цифрових документів і звітів на Laravel 13 та стенд для порівняння продуктивності
чотирьох способів запуску одного й того самого застосунку:

| Режим | Сервер | Адреса |
|---|---|---|
| `fpm` | Nginx + PHP-FPM | http://localhost:8081 |
| `roadrunner` | Laravel Octane + RoadRunner | http://localhost:8082 |
| `swoole` | Laravel Octane + Swoole | http://localhost:8083 |
| `frankenphp` | Laravel Octane + FrankenPHP | http://localhost:8084 |

План робіт: [docs/PLAN.md](docs/PLAN.md).

## Що потрібно

- Docker Desktop 4.x з Docker Compose v2 (на Windows обов'язково з бекендом **WSL2**);
- Git;
- (опційно) `make` у Linux/WSL або PowerShell на Windows.

PHP і Composer на самій машині не потрібні, усе працює в контейнерах.

## Запуск з нуля

```bash
git clone https://github.com/Octopus663/diploma.git
cd diploma

# 1. Зібрати образи (перший раз 5–10 хвилин: компілюється Swoole)
docker compose build app fpm frankenphp

# 2. Підняти БД і Redis та створити таблиці
docker compose run --rm app php artisan migrate --force

# 3. Запустити один з режимів, наприклад FPM
docker compose --profile fpm up -d

# 4. Перевірити
curl http://localhost:8081/api/ping
```

Відповідь однакова в усіх режимах:

```json
{"status":"ok","app":"Diploma","laravel":"13.x.x","php":"8.4.x"}
```

### Тестові дані

```bash
# Повний обсяг (~1 хв): 5 000 контрагентів, 100 000 замовлень, ~500 000 позицій, 30 000 документів
docker compose run --rm app php artisan migrate:fresh --seed --force

# Менший обсяг для швидких перевірок
docker compose run --rm -e SEED_SCALE=0.05 app php artisan migrate:fresh --seed --force
```

Сидер детермінований (фіксований seed `SEED_RANDOM=42`), тому дані однакові при кожному запуску.

Користувачі (пароль у всіх `password`):

| Email | Роль |
|---|---|
| `admin@diploma.test` | admin (може видаляти) |
| `manager@diploma.test` | manager |
| `bench01..bench20@diploma.test` | manager, для k6 |

### API

Токен Sanctum: `POST /api/auth/token` з `email`, `password`, далі заголовок `Authorization: Bearer <token>`.

| Метод | Шлях |
|---|---|
| GET | `/api/ping` (публічний), `/api/me` |
| CRUD | `/api/counterparties`, `/api/orders`, `/api/documents` |
| GET | `/api/templates`, `/api/products` |

`POST /api/documents` з `template_id` і `order_id` формує документ (дані звіту в `payload`).

### Сценарії навантаження

| # | Маршрут | Що вимірює |
|---|---|---|
| 1 | `GET /api/ping` (без токена) | накладні витрати самого фреймворку |
| 1а | `GET /api/me` | те саме + автентифікація Sanctum (запит до БД за токеном) |
| 2 | `GET /api/bench/db/orders/{id}`, `GET /api/bench/db/counterparties/{id}/orders?limit=20` | Eloquent: eager loading, JOIN-и, агрегати |
| 3 | `GET /api/bench/cache/counterparties/{id}`, `POST .../{id}/hit` | читання і запис агрегатів у Redis |
| 4 | `GET/POST /api/bench/compute/report?rows=10000&seed=42` | генерація, агрегування, сортування і серіалізація великого масиву |

Сценарії 2–4 потребують токен. Перед вимірюваннями кешу: `docker compose run --rm app php artisan bench:warm-cache`.
Відповіді детерміновані: `./scripts/smoke.sh` перевіряє, що в усіх режимах вони однакові.

Перевірка всіх запущених режимів: `./scripts/smoke.sh`.

Перемикання режиму (під час вимірювань завжди працює лише один):

```bash
docker compose --profile '*' down
docker compose --profile swoole up -d
curl http://localhost:8083/api/ping
```

Те саме через `make` (Linux/WSL): `make up-fpm`, `make up-rr`, `make up-swoole`, `make up-franken`, `make down`, `make ping`, `make test`.

Те саме через PowerShell: `.\scripts\stand.ps1 up swoole`, `.\scripts\stand.ps1 down`, `.\scripts\stand.ps1 ping`.

## Windows + Docker Desktop (WSL2)

Від цих налаштувань залежить якість результатів:

1. **Docker Desktop → Settings → General → Use the WSL 2 based engine** увімкнено.
2. Встановіть Ubuntu з Microsoft Store (`wsl --install -d Ubuntu`) і в **Settings → Resources → WSL integration** увімкніть її.
3. **Клонуйте репозиторій всередину файлової системи WSL**, а не на диск `C:`:
   ```bash
   # у терміналі Ubuntu (WSL)
   cd ~
   git clone https://github.com/Octopus663/diploma.git
   ```
   Шлях у провіднику Windows: `\\wsl$\Ubuntu\home\<user>\diploma`. Код з `C:\...` монтується через
   повільний міжсистемний шар, і це штучно погіршує результати.
4. Обмежте ресурси WSL, щоб вони не «плавали» між прогонами. Файл `%UserProfile%\.wslconfig`:
   ```ini
   [wsl2]
   processors=4
   memory=6GB
   ```
   Після зміни виконайте `wsl --shutdown` і перезапустіть Docker Desktop.
5. Під час вимірювань ноутбук від мережі, схема живлення «Максимальна продуктивність», важкі програми закриті.

Усі команди з README виконуйте в терміналі Ubuntu (WSL) з теки проєкту.

## Вимірювання (k6)

```bash
# Швидкий прогін (~40 хв): 4 режими × 6 сценаріїв × VU 10/100/300, по 1 повтору
QUICK=1 ./bench/run.sh

# Повний прогін (кілька годин, оцінка часу виводиться на старті)
./bench/run.sh

# Вибірково
MODES="fpm swoole" SCENARIOS="ping database" VUS_LIST="10 100" REPEATS=1 DURATION=30s ./bench/run.sh

# Тривалий тест пам'яті (30 хв на режим)
SOAK=1 ./bench/run.sh
```

`run.sh` сам піднімає БД, накочує міграції, видає токени (`bench:tokens`), прогріває кеш, по черзі запускає
кожен режим (інші зупинені), робить прогрів і для кожного прогону зберігає підсумок k6 (`*.json`) і CPU/RAM
контейнера щосекунди (`*.stats.csv`) у `bench/results/<дата>/`. k6 працює в контейнері в тій самій мережі
compose і звертається до застосунку напряму (`http://swoole:8000`).

Сценарії k6 лежать у `bench/k6/`: `ping`, `auth`, `database`, `cache`, `compute`, `mixed`, а також `arrival`
(постійна частота запитів для порівняння затримок) і `soak`. Параметри стенду і калібрування: `bench/config.md`.

Окремий сценарій вручну:

```bash
docker compose --profile swoole up -d
docker compose run --rm -T app php artisan bench:tokens > bench/results/.tokens.json
docker compose --profile bench run --rm -T -e TARGET=http://swoole:8000 -e TOKENS=/results/.tokens.json \
  -e VUS=50 -e DURATION=30s k6 run database.js
```

## Моніторинг

```bash
docker compose --profile monitoring up -d
```

- Grafana: http://localhost:3000 (без входу), дашборд «Diploma: стенд Octane»: CPU, пам'ять, мережа, процеси кожного контейнера.
- Prometheus: http://localhost:9090.
- Метрики знімає власний легкий експортер `monitoring/exporter/exporter.py` з Docker API (те саме, що `docker stats`).
  cAdvisor не використовується, бо не працює з containerd image store, який Docker Desktop вмикає за замовчуванням.

Якщо моніторинг запущений під час `bench/run.sh`, наприкінці автоматично виконується
`python3 bench/export_metrics.py bench/results/<дата>`: для кожного прогону з'являється `*.prom.csv` (CPU і пам'ять
застосунку щосекунди). Без моніторингу аналіз використовує `*.stats.csv` з `docker stats`.

Моніторинг займає до 1.25 CPU хоста, але не ділить ліміт з контейнером застосунку.

## Ресурси контейнерів

| Контейнер | CPU | RAM | Змінні |
|---|---|---|---|
| застосунок (будь-який режим) | 1.0 | 512 MB | `APP_CPUS`, `APP_MEM` |
| PostgreSQL 17 | 1.0 | 1 GB | `DB_CPUS`, `DB_MEM` |
| Redis 7 | 0.5 | 256 MB | `REDIS_CPUS`, `REDIS_MEM` |

Кількість воркерів Octane: `OCTANE_WORKERS` (типово 2), перезапуск воркера після `OCTANE_MAX_REQUESTS` запитів (типово 500).
PHP-FPM: `pm = static`, `pm.max_children = 8` у `docker/fpm/www.conf`. Остаточні значення підбираються калібрувальним
прогоном на етапі 4 і фіксуються в `bench/config.md`.

Значення можна перевизначити у файлі `.env` в корені репозиторію (поруч з `docker-compose.yml`), наприклад:

```ini
APP_MEM=768m
OCTANE_WORKERS=4
```

## Структура

```
app/          Laravel-застосунок (один код для всіх режимів)
docker/       Dockerfile, nginx, php-fpm, RoadRunner, entrypoint
docs/         план і методика
bench/        сценарії k6 і результати (з'являться на етапі 4)
scripts/      допоміжні скрипти для Windows
```

## Як влаштовані режими

- `docker/php/Dockerfile` збирає **один образ** (PHP 8.4 + pdo_pgsql, redis, opcache, pcntl, sockets, intl, zip, swoole,
  nginx і бінарник RoadRunner). Режими `fpm`, `roadrunner` і `swoole` використовують саме його.
- `docker/frankenphp/Dockerfile` базується на офіційному образі FrankenPHP (там PHP вбудований у сервер), але має ту саму
  версію PHP, ті самі розширення, `php.ini` і код.
- `docker/php/entrypoint.sh` кешує конфігурацію (`config:cache`, `route:cache`, `event:cache`) і запускає потрібний сервер
  за змінною `SERVER_MODE`.

## Корпоративний проксі

Якщо збірка падає на завантаженні пакетів через проксі з власним сертифікатом, покладіть його `.crt` у `docker/certs/`
(тека в `.gitignore`), він встановиться в образ. Зазвичай це не потрібно.
