.PHONY: help setup up down build install key migrate test verify no-dev-check security security-policy-test acceptance queue logs shell

help: ## Show available commands
	@awk 'BEGIN {FS = ":.*## "} /^[a-zA-Z_-]+:.*## / {printf "%-22s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

setup: ## Prepare configuration, build, audit, install, migrate and start
	sh scripts/dev/setup.sh

up: ## Start services and wait for health; preserve keys and data
	docker compose up -d --wait --wait-timeout 180

down: ## Stop services; preserve volumes
	docker compose down

build: ## Build app and nginx with the host user IDs
	APP_UID="$$(id -u)" APP_GID="$$(id -g)" docker compose build app nginx

install: ## Audit and install locked dependencies in an isolated one-off container
	sh scripts/dev/composer-install-check.sh

key: ## Fill only a missing key and prepare runtime; preserve existing settings
	docker compose run --rm --no-deps -T app php < scripts/dev/prepare-runtime.php

migrate: ## Explicitly apply pending migrations
	docker compose run --rm --no-deps -T app php artisan migrate --no-interaction

test: ## Run Laravel tests
	docker compose exec -T app php artisan test

verify: ## Verify isolated delivery, persistence and stopped-worker behavior
	sh scripts/dev/verify.sh

no-dev-check: ## Verify a clean installation without development dependencies
	sh scripts/dev/no-dev-check.sh

acceptance: ## Verify a clean copy twice, then evaluate security
	sh scripts/dev/acceptance.sh

security: ## Collect and evaluate security checks; pending evidence fails acceptance
	sh scripts/dev/security-check.sh

security-policy-test: ## Test rejection of unsafe or incomplete security evidence
	sh scripts/dev/security-policy-test.sh

queue: ## Follow the existing Compose worker logs
	docker compose logs -f worker

logs: ## Follow application, worker and nginx logs
	docker compose logs -f app worker nginx

shell: ## Open a shell as the application user
	docker compose exec app sh
