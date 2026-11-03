# Короткі команди для Linux/WSL. На Windows без make дивіться scripts/stand.ps1.
COMPOSE = docker compose

.PHONY: build up-fpm up-rr up-swoole up-franken down migrate seed fresh test ping logs

build:
	$(COMPOSE) build app fpm frankenphp

up-fpm:
	$(COMPOSE) --profile fpm up -d --build

up-rr:
	$(COMPOSE) --profile roadrunner up -d --build

up-swoole:
	$(COMPOSE) --profile swoole up -d --build

up-franken:
	$(COMPOSE) --profile frankenphp up -d --build

down:
	$(COMPOSE) --profile '*' down --remove-orphans

migrate:
	$(COMPOSE) run --rm app php artisan migrate --force

seed:
	$(COMPOSE) run --rm app php artisan db:seed --force

fresh:
	$(COMPOSE) run --rm app php artisan migrate:fresh --seed --force

test:
	$(COMPOSE) run --rm app php artisan test

ping:
	@for p in 8081 8082 8083 8084; do printf "%s: " $$p; curl -s --max-time 3 localhost:$$p/api/ping || printf "недоступний"; echo; done

logs:
	$(COMPOSE) --profile '*' logs -f --tail=100
