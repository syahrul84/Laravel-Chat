.PHONY: help up down build restart bash artisan test migrate fresh logs composer npm tinker

# Default: show help
help:
	@echo ""
	@echo "  Laravel Real-Time Chat — Docker Commands"
	@echo "  ─────────────────────────────────────────"
	@echo "  make up              Start all containers (detached)"
	@echo "  make down            Stop and remove containers"
	@echo "  make build           Rebuild all images (no cache)"
	@echo "  make restart         Restart all containers"
	@echo ""
	@echo "  make bash            Shell into the app container"
	@echo "  make tinker          Open Laravel Tinker"
	@echo ""
	@echo "  make artisan cmd=<cmd>    Run artisan command"
	@echo "  make composer cmd=<cmd>   Run composer command"
	@echo "  make npm cmd=<cmd>        Run npm command (node container)"
	@echo ""
	@echo "  make test            Run PHPUnit test suite"
	@echo "  make migrate         Run migrations"
	@echo "  make fresh           Fresh migrate + seed"
	@echo ""
	@echo "  make logs [service=<name>]   Tail logs (all or specific service)"
	@echo ""

# ─── Docker Lifecycle ─────────────────────────────────────────────────────────

up:
	docker-compose up -d

down:
	docker-compose down

build:
	docker-compose build

restart:
	docker-compose restart

# ─── Shell Access ─────────────────────────────────────────────────────────────

bash:
	docker-compose exec app sh

tinker:
	docker-compose exec app php artisan tinker

# ─── Artisan ──────────────────────────────────────────────────────────────────

artisan:
	docker-compose exec app php artisan $(cmd)

# ─── Tests ────────────────────────────────────────────────────────────────────

test:
	docker-compose exec app php artisan test

test-filter:
	docker-compose exec app php artisan test --filter=$(filter)

# ─── Database ─────────────────────────────────────────────────────────────────

migrate:
	docker-compose exec app php artisan migrate

fresh:
	docker-compose exec app php artisan migrate:fresh --seed

# ─── Logs ─────────────────────────────────────────────────────────────────────

logs:
ifdef service
	docker-compose logs -f $(service)
else
	docker-compose logs -f
endif

# ─── Composer ─────────────────────────────────────────────────────────────────

composer:
	docker-compose exec app composer $(cmd)

# ─── Node / NPM ───────────────────────────────────────────────────────────────

npm:
	docker-compose exec -u root app npm $(cmd)

npm-build:
	docker-compose exec app ./node_modules/.bin/vite build

npm-dev:
	docker-compose exec node npm run dev

# ─── Setup (first-time) ───────────────────────────────────────────────────────

setup:
	cp -n .env.example .env || true
	make build
	make up
	docker-compose exec -T -u root app composer install
	docker-compose exec -T -u root app npm install
	make artisan cmd=key:generate
	make migrate
	@echo ""
	@echo "  Setup complete! Visit http://localhost"
	@echo ""
