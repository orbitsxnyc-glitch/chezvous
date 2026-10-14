# ChezVous : instructions projet

Plateforme immobilière de confiance (Sénégal), lancement ciblé sur la location résidentielle à Dakar. Monorepo :
- `backend/` : Laravel (version à figer après vérification de compatibilité), PHP 8.3+, PostgreSQL 16+ avec PostGIS, Redis, Sanctum, spatie/laravel-permission, Pest
- `web/` : Next.js (App Router), TypeScript strict, Tailwind, rendu serveur pour les pages publiques
- `mobile/` : Flutter (Android et iOS), consomme la même API
- `docs/` : documentation

Références : `docs/specs-mvp.md` (identifiants AUTH-, ANN-, RCH-, AGC-, DEM-, FAV-, COM-, NOT-, ADM-), complété et corrigé par `docs/specs-addendum-passeport.md` (Passeport du Bien : séparation `properties` / `listings`, mandats, contrôles, fraîcheur). Décisions et questions ouvertes : `docs/DECISIONS.md`.

## Règles
1. Autorisation côté serveur via Policies, jamais seulement dans l'interface.
2. Aucun prix, quota, durée ou seuil de fraîcheur en dur : configuration ou tables `plans` / `settings`.
3. Transitions d'état via services dédiés, avec écriture dans `audit_logs`.
4. Aucun paiement en ligne au MVP. Ne jamais marquer une souscription comme payée sans encaissement confirmé par un admin.
5. Devise XOF, montants entiers. Interface en français, i18n prête.
6. Un bien (`properties`) existe une fois ; les annonces (`listings`) y sont rattachées. Les contrôles de vérification sont explicites et datés ; un document contrôlé n'est pas une garantie juridique.
7. Tests Pest pour chaque service métier et chaque Policy ; Pint et PHPStan avant commit.
8. Commits en français, Conventional Commits, une branche par phase.
9. Aucun secret, `.env` réel, justificatif ou pièce d'identité dans Git.
10. Pas de push distant, de service payant ni de compte externe sans accord explicite.
11. Avant de coder : auditer l'existant, proposer un plan, attendre validation.
