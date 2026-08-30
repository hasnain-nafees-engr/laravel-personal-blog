# Developer entry points. Everything runs inside Docker - no host PHP needed.
# `docker compose up` automatically merges docker-compose.override.yml (dev).

COMPOSE      := docker compose
COMPOSER_IMG := composer:2
UID          := $(shell id -u)
GID          := $(shell id -g)

.DEFAULT_GOAL := help

.PHONY: help up down restart ps logs shell artisan tinker migrate fresh seed \
        test test-coverage lint fix build prod-up prod-down deps composer npm db-shell

help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

deps: ## Install Composer deps via container (runs automatically when vendor/ is missing)
	@if [ ! -d vendor ]; then \
		echo ">> vendor/ missing - installing Composer dependencies..."; \
		docker run --rm -u "$(UID):$(GID)" -e HOME=/tmp -e COMPOSER_CACHE_DIR=/cache \
			-v laravel-blog-composer-cache:/cache -v "$$PWD:/app" -w /app \
			$(COMPOSER_IMG) composer install --prefer-dist --no-interaction; \
	fi

up: deps ## Build (if needed) and start the dev stack
	$(COMPOSE) up -d --build
	@echo ">> App: http://localhost:$${APP_PORT:-8000}  |  Mailpit: http://localhost:$${MAILPIT_UI_PORT:-8025}"

down: ## Stop the stack (keeps volumes/data)
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

ps: ## Show service status + health
	$(COMPOSE) ps

logs: ## Tail logs of every service (Ctrl+C to quit)
	$(COMPOSE) logs -f --tail=100

shell: ## Open a shell inside the app container
	$(COMPOSE) exec app sh

artisan: ## Run an artisan command: make artisan cmd="route:list"
	$(COMPOSE) exec app php artisan $(cmd)

tinker: ## Laravel REPL
	$(COMPOSE) exec app php artisan tinker

migrate: ## Run pending migrations
	$(COMPOSE) exec app php artisan migrate

fresh: ## DROP everything, re-migrate and seed demo data
	$(COMPOSE) exec app php artisan migrate:fresh --seed

seed: ## Seed demo data
	$(COMPOSE) exec app php artisan db:seed

test: ## Run the Pest test suite
	$(COMPOSE) exec app php artisan test

test-coverage: ## Run tests with a coverage report (needs XDEBUG_MODE=coverage)
	$(COMPOSE) exec -e XDEBUG_MODE=coverage app php artisan test --coverage

lint: ## Code style check (Pint) + static analysis (Larastan)
	$(COMPOSE) exec app sh -c "./vendor/bin/pint --test && { [ -f vendor/bin/phpstan ] && ./vendor/bin/phpstan analyse --memory-limit=1G || echo 'phpstan not installed yet'; }"

fix: ## Auto-fix code style with Pint
	$(COMPOSE) exec app ./vendor/bin/pint

composer: ## Run composer inside the app container: make composer cmd="require foo/bar"
	$(COMPOSE) exec app composer $(cmd)

npm: ## Run npm in the Vite container: make npm cmd="install foo"
	$(COMPOSE) run --rm vite npm $(cmd)

db-shell: ## psql into the DB (works in both DB modes)
	$(COMPOSE) exec app sh -c 'PGPASSWORD=$$DB_PASSWORD psql -h $$DB_HOST -p $$DB_PORT -U $$DB_USERNAME $$DB_DATABASE' 2>/dev/null \
		|| $(COMPOSE) exec postgres psql -U $$(grep ^DB_USERNAME .env | cut -d= -f2) $$(grep ^DB_DATABASE .env | cut -d= -f2)

build: ## Rebuild images without starting
	$(COMPOSE) build

prod-up: ## Start the PRODUCTION-shaped stack (no dev overrides)
	$(COMPOSE) -f docker-compose.yml up -d --build

prod-down: ## Stop the production-shaped stack
	$(COMPOSE) -f docker-compose.yml down
