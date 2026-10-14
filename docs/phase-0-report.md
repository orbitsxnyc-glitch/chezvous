# Phase 0 : rapport final

Date : 2026-10-14. Dépôt local, aucun push.

## Livré

- Monorepo : `backend/` (Laravel 13), `web/` (Next.js 16.4, React 19.3), `mobile/` (Flutter 3.47, Android et iOS), `docs/`, `infra/`.
- Services locaux natifs : PostgreSQL 17.11 + PostGIS 3.6, Redis 8.10 (via Homebrew). Bases `chezvous` et `chezvous_test`.
- Backend : Sanctum 4.3, spatie/laravel-permission 8.3, Pest 4.7, Larastan 3.13 (niveau 6), Pint, predis.
- Documentation : README, CLAUDE.md, ARCHITECTURE, DECISIONS, SECURITY-CHECKLIST, QUALITY, specs MVP et addendum Passeport.
- `Makefile` (`setup`, `dev`, `lint`, `test`, `check`), `infra/docker-compose.yml`, `.github/workflows/ci.yml`.

## Vérifié par exécution

- `make check` : exit 0 (Pint, PHPStan 0 erreur, Pest 2/2 sur PostgreSQL, ESLint, typecheck, build Next.js, `dart format`, `flutter analyze`, `flutter test` 1/1).
- `SELECT postgis_version()` : 3.6 ; Redis : écriture/lecture OK depuis Laravel.
- `composer audit` : aucune vulnérabilité.
- `.env`, `vendor/`, `node_modules/` non suivis par Git.

## Non vérifié ou non fait

- `docker-compose.yml` : non testé (Docker introuvable sur la machine).
- CI GitHub Actions : écrite, jamais exécutée.
- Build Android (licences non acceptées) et build iOS : non tentés.
- `npm audit` : 1 alerte haute (`braces`, chaîne `eslint-config-next`, développement uniquement) ; correctif `--force` refusé car rétrograderait Next.js.
- `make setup` depuis un clone vierge : non rejoué.
- Aucune migration métier, seeders de rôles ni super admin (Phase 1).

## Incident

L'installation de PostGIS a mis à jour des dépendances Homebrew et cassé Node ; réparé par `brew upgrade node` (26.7.0 vers 26.11.1).

## Actions restantes pour clôturer la Phase 0

1. Décision du porteur sur le push vers le dépôt public, puis exécution réelle de la CI.
2. Acceptation des licences Android, installation de Docker, test de `docker-compose`.
3. Sort de `backend/CLAUDE.md`, `backend/AGENTS.md`, de `ca6f4a01….jpg` et du fichier de spécifications d'origine en doublon.
