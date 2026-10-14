# ChezVous (nom provisoire)

Plateforme immobilière de confiance pour le marché sénégalais, avec un lancement ciblé sur la **location résidentielle à Dakar**.

> Principe directeur : mieux vaut 500 biens réellement disponibles et fiables que 20 000 annonces obsolètes.

**État actuel : Phase 0 (fondations).** Aucune fonctionnalité métier n'est développée. Les dossiers applicatifs sont vides en attendant la validation de la Phase 0.

## Structure du monorepo

| Dossier | Contenu prévu |
|---|---|
| `backend/` | API REST Laravel (monolithe modulaire) |
| `web/` | Next.js : site public, espace professionnel, back-office |
| `mobile/` | Application Flutter (Android et iOS) |
| `docs/` | Architecture, décisions, audit, sécurité, présentation source |
| `infra/` | Configuration d'infrastructure, si nécessaire |

## Documentation

- [Audit de l'environnement](docs/phase-0-environment-audit.md)
- [Spécifications fonctionnelles MVP](docs/specs-mvp.md) et [addendum Passeport du Bien](docs/specs-addendum-passeport.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Décisions et questions ouvertes](docs/DECISIONS.md)
- [Checklist de sécurité](docs/SECURITY-CHECKLIST.md)
- [Qualité et tests](docs/QUALITY.md)
- Présentation source : `docs/ChezVous_Presentation_Concept_Immobilier_Senegal.pptx`

## Démarrage

Prérequis (macOS, installation native testée) : PHP 8.3+ avec `pdo_pgsql`, Composer, Node.js, Flutter, PostgreSQL 17 avec PostGIS, Redis.

**Base de données (une fois)**
```bash
createdb chezvous && psql -d chezvous -c "CREATE EXTENSION postgis;"
createdb chezvous_test && psql -d chezvous_test -c "CREATE EXTENSION postgis;"
```

**Backend** (`backend/`)
```bash
cp .env.example .env && composer install && php artisan key:generate
php artisan migrate
php artisan serve          # API locale
php artisan queue:work     # traitement des photos (obligatoire hors tests)
php artisan test           # Pest
./vendor/bin/pint --test && ./vendor/bin/phpstan analyse
```

**Web** (`web/`)
```bash
npm install
npm run dev                # serveur de développement
npm run lint && npm run typecheck && npm run build
```

**Mobile** (`mobile/`)
```bash
flutter pub get
flutter analyze && flutter test
flutter run
```

Alternative Docker : `infra/docker-compose.yml` (non testé, voir les commentaires du fichier).

**Commande unique :** `make setup` puis `make check` (lint, analyse, tests, build) ; `make dev` lance l'API et le web. La CI (`infra/ci/github-ci.yml` (à déplacer vers `.github/workflows/ci.yml` quand le jeton GitHub aura le droit `workflow`)) est écrite mais n'a jamais été exécutée.

## Règles du dépôt

- Ne jamais versionner de fichier `.env` réel, de clé privée, de justificatif ou de document d'identité. Seuls les fichiers `.env.example` sont autorisés.
- Aucun envoi vers un dépôt distant sans accord du porteur du projet.
