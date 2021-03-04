COMPOSE=docker-compose
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

up:
	@${COMPOSE} up -d

down:
	@${COMPOSE} down

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migrations:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load

exec-php:
	@${PHP} /bin/sh

fix:
	@${PHP} vendor/bin/php-cs-fixer fix

encore-dev:
	@${COMPOSE} run node yarn encore dev --watch

encore-prod:
	@${COMPOSE} run node yarn encore production
