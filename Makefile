.PHONY: up down logs migrate test quality race acceptance

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f app worker

migrate:
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

test:
	docker compose exec -T database sh -c "psql -U dgs -tAc \"SELECT 1 FROM pg_database WHERE datname='dgs_test'\" | grep -q 1 || createdb -U dgs dgs_test"
	docker compose exec -T -e APP_ENV=test -e DATABASE_URL='postgresql://dgs:dgs@database:5432/dgs_test?serverVersion=17&charset=utf8' app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	docker compose exec -T -e APP_ENV=test -e DATABASE_URL='postgresql://dgs:dgs@database:5432/dgs_test?serverVersion=17&charset=utf8' app php bin/phpunit

quality:
	docker compose exec -T app vendor/bin/php-cs-fixer fix --dry-run --diff
	docker compose exec -T app vendor/bin/phpstan analyse --no-progress --memory-limit=512M
	docker compose exec -T app php bin/console doctrine:schema:validate
	docker compose exec -T app php bin/console lint:twig templates
	docker compose exec -T app php bin/console lint:container

race:
	./scripts/race-test.sh

acceptance: test quality race
