# Короткі команди для docker compose.  `make` — список команд.
# Рецепти починаються з ">" замість табуляції (.RECIPEPREFIX): файл не ламається,
# якщо його скопіювати з браузера, де таби перетворюються на пробіли.
.RECIPEPREFIX := >
-include .env

DC   := docker compose
EXEC := $(DC) exec app
RUN  := $(DC) run --rm --no-deps app
NODE := $(DC) run --rm --no-deps node

DB_CLI := mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"

.DEFAULT_GOAL := help
.PHONY: help init build up down restart rebuild ps logs sh shell composer \
        migrate admin test stan cs cs-fix db reset-db \
        front-install front-dev front-build clean

help: ## Список команд
> @grep -hE '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  \033[36m%-13s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------- старт

init: ## Перший запуск: .env-файли, образ, composer install, старт, міграції
> @created=0; \
  test -f .env     || { cp .env.example .env;         echo ">> Створено .env"; created=1; }; \
  test -f app/.env || { cp app/.env.example app/.env; echo ">> Створено app/.env"; created=1; }; \
  if [ $$created = 1 ]; then echo ">> Перевір значення (APP_SECRET, паролі) і запусти make init ще раз."; exit 1; fi
> @if [ "$(COMPOSE_PROJECT_NAME)" = "changeme" ]; then echo ">> У .env досі changeme у COMPOSE_PROJECT_NAME."; exit 1; fi
> mkdir -p app $(HOME)/.cache/composer
> $(DC) build
> $(RUN) composer install
> $(DC) up -d --wait
> @if [ -f app/bin/migrate.php ]; then $(EXEC) php bin/migrate.php; fi
> @echo ">> Готово: http://localhost:$(HOST_APP_PORT)"

# ---------------------------------------------------------------- контейнери

build: ## Перезібрати PHP-образ (після зміни PHP_VERSION / PHP_EXTENSIONS / Dockerfile)
> $(DC) build --pull

up: ## Запустити (чекає, поки БД стане healthy)
> $(DC) up -d --wait

down: ## Зупинити все, включно з node (дані БД зберігаються)
> $(DC) --profile '*' down

restart: ## Перезапустити контейнери (напр. cron після зміни crontab)
> $(DC) restart

rebuild: ## Перезібрати образ і пересоздати контейнери
> $(DC) up -d --build --wait

ps: ## Стан контейнерів
> $(DC) --profile '*' ps

logs: ## Логи всіх сервісів; один сервіс: make logs s=cron
> $(DC) logs -f $(s)

# ---------------------------------------------------------------- PHP

sh: ## bash у контейнері app
> $(EXEC) bash

shell: sh ## Синонім sh

composer: ## Composer: make composer c="require vendor/package"
> $(EXEC) composer $(c)

migrate: ## Застосувати міграції (bin/migrate.php, T04)
> $(EXEC) php bin/migrate.php

admin: ## Створити адміна: make admin email=a@b.c password=... (T04)
> $(EXEC) php bin/create-admin.php $(email) $(password)

test: ## Тести (на тестовій БД): make test c="--filter=RouterTest"
> $(EXEC) vendor/bin/phpunit $(c)

stan: ## Статичний аналіз PHPStan
> $(EXEC) vendor/bin/phpstan analyse --memory-limit=512M

cs: ## Перевірка стилю (dry-run, нічого не змінює)
> $(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Виправити стиль
> $(EXEC) vendor/bin/php-cs-fixer fix

# ---------------------------------------------------------------- дані

db: ## Консоль MySQL
> $(DC) exec db sh -c '$(DB_CLI)'

reset-db: ## ВИДАЛИТИ всі дані (том БД) і підняти з нуля
> $(DC) --profile '*' down -v
> $(DC) up -d --wait

# ---------------------------------------------------------------- фронтенд (app/frontend, T10)

front-install: ## npm install у app/frontend
> $(NODE) npm install

front-dev: ## Vite dev server (http://localhost:HOST_VITE_PORT)
> $(DC) --profile front up node

front-build: ## Зібрати Vue у app/public/assets
> $(NODE) npm run build

clean: ## Знести все: контейнери, томи, локальний образ проєкту
> $(DC) --profile '*' down -v --rmi local --remove-orphans
