# Everything runs inside Docker (no PHP on the host).
EXEC = docker compose exec app

.PHONY: up down sh test lint format analyse ci fresh build

up:
	docker compose up -d --build

down:
	docker compose down

sh:
	$(EXEC) bash

test:
	$(EXEC) composer test

lint:
	$(EXEC) composer lint

format:
	$(EXEC) composer format

analyse:
	$(EXEC) composer analyse

ci:
	$(EXEC) composer ci

fresh:
	$(EXEC) php artisan migrate:fresh --seed

build:
	$(EXEC) npm run build
