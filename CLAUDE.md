# ChezVous : instructions projet

Plateforme immobilière de confiance (Sénégal), lancement ciblé sur les séjours meublés réservables à Dakar (phase 17, `docs/phase-17-plan-sejours.md`), la location longue durée et la vente restant disponibles. Monorepo :
- `backend/` : Laravel (version à figer après vérification de compatibilité), PHP 8.3+, PostgreSQL 16+ avec PostGIS, Redis, Sanctum, spatie/laravel-permission, Pest
- `web/` : Next.js (App Router), TypeScript strict, Tailwind, rendu serveur pour les pages publiques
- `mobile/` : Flutter (Android et iOS), consomme la même API
- `docs/` : documentation

Références : `docs/specs-mvp.md` (identifiants AUTH-, ANN-, RCH-, AGC-, DEM-, FAV-, COM-, NOT-, ADM-), complété et corrigé par `docs/specs-addendum-passeport.md` (Passeport du Bien : séparation `properties` / `listings`, mandats, contrôles, fraîcheur). Décisions et questions ouvertes : `docs/DECISIONS.md`.

## Règles
1. Autorisation côté serveur via Policies, jamais seulement dans l'interface.
2. Aucun prix, quota, durée ou seuil de fraîcheur en dur : configuration ou tables `plans` / `settings`.
3. Transitions d'état via services dédiés, avec écriture dans `audit_logs`.
4. Tout paiement passe par l'interface `PaymentGateway`. Tant qu'aucun prestataire n'est contractualisé, seul l'adaptateur « encaissement manuel confirmé par un admin » est actif ; Wave et Orange Money viendront ensuite, sur un compte marchand ouvert par le porteur du projet. Ne jamais marquer un paiement ou une souscription comme payé sans confirmation vérifiée côté serveur (webhook signé ou admin) ; le retour du navigateur n'est jamais une preuve. (Règle modifiée le 18/10/2026 avec l'accord du porteur, phase 17 séjours.)
5. Devise XOF, montants entiers. Interface en français, i18n prête.
6. Un bien (`properties`) existe une fois ; les annonces (`listings`) y sont rattachées. Les contrôles de vérification sont explicites et datés ; un document contrôlé n'est pas une garantie juridique.
7. Tests Pest pour chaque service métier et chaque Policy ; Pint et PHPStan avant commit.
8. Commits en français, Conventional Commits, une branche par phase.
9. Aucun secret, `.env` réel, justificatif ou pièce d'identité dans Git.
10. Pas de push distant, de service payant ni de compte externe sans accord explicite.
11. Avant de coder : auditer l'existant, proposer un plan, attendre validation.
