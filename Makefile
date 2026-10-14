# Commandes de développement ChezVous (installation native). `make help` pour la liste.
.PHONY: help setup dev check test lint

help:
	@echo "make setup  : installe les dépendances des trois composants"
	@echo "make dev    : lance l'API (8000), le worker de files (photos) et le web (3000)"
	@echo "make check  : lint, analyse statique, tests et build de tout le monorepo"

setup:
	cd backend && composer install && ([ -f .env ] || cp .env.example .env) && php artisan key:generate --ansi
	cd web && npm install
	cd mobile && flutter pub get

dev:
	cd backend && php artisan serve & cd backend && php artisan queue:work & cd web && npm run dev

lint:
	cd backend && ./vendor/bin/pint --test && ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	cd web && npm run lint && npm run typecheck && npm test
	cd mobile && dart format --set-exit-if-changed lib test && flutter analyze

test:
	cd backend && php artisan test
	cd mobile && flutter test

check: lint test
	cd web && npm run build
