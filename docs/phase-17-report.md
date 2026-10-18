# Phase 17 — Séjours (en cours)

Branche `phase-17-sejours`. Plan et écarts : `docs/phase-17-plan-sejours.md`. Décisions du porteur (18/10/2026) : garder Laravel ; règle 4 modifiée (interface `PaymentGateway`, encaissement manuel d'abord, Wave / Orange Money ensuite sur compte marchand ouvert par le porteur).

## Lot 1 — Unités, tarifs, calendrier (livré)
- **Tables** : `units`, `rate_plans` (un plan par unité, XOF entiers), `rate_overrides` (intervalle demi-ouvert, priorité), `calendar_blocks`. Contraintes SQL : dates cohérentes, devise XOF, statuts valides.
- **Services** (`app/Services/Stays`) : `UnitService` (création, tarif, exceptions de prix, transitions d'état avec audit — une unité n'est activée qu'avec un tarif), `CalendarService` (fermetures, détection de conflit), `PricingService` (prix par nuit, priorité des exceptions, ménage, durées min/max), `StayDates` (intervalle `[arrivée, départ)`).
- **Policy** `UnitPolicy` : propriétaire, gérant d'agence, agent attribué ; tout le reste en 404.
- **API hôte** `/api/v1/host/units` : liste, création, fiche, modification, tarif, exceptions, statut, fermetures, aperçu prix + disponibilité.
- **Configuration** : durées maximales dans `config/chezvous.php` (`stays.*`), aucune valeur en dur.
- **Tests** : 19 (calcul de prix, chevauchements aux bornes, limites configurables, transitions, droits inter-comptes, agence).

## À venir
2. Recherche par dates et invités, fiche d'unité, devis figé (frais plateforme et taxes depuis les réglages).
3. Demande de réservation → acceptation → verrou temporaire → confirmation (contrainte d'exclusion GiST sur les occupations), outbox.
4. `PaymentGateway`, adaptateur manuel, journal d'événements idempotent, remboursements.
5. Écrans voyageur / hôte (web puis mobile).
6. Contrôles par catégorie, administration, rapprochement.
