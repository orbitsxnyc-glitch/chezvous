# Phase 17 — Plan : réorientation vers les séjours (proposition, en attente de validation)

Sources : `docs/CHEZVOUS_Plan_Complet_Developpement.pdf` (dossier maître, 18 octobre 2026) et `docs/ChezVous_2026_Presentation_CORRIGEE.pdf`. Décision du porteur : prendre les deux PDF comme référence à la lettre.

## Ce que les PDF demandent
Plateforme de **séjours meublés réservables** (Dakar d'abord) : voyageur / hôte-agence / administration ; réservation atomique sans double occupation ; devis figé ; paiement via un prestataire (PSP) vérifié côté serveur ; contrôles différenciés (pas de badge global) ; location longue durée et vente **préparées dans le schéma mais non construites**.

## Écarts avec l'existant (à trancher)
| Sujet | PDF | Existant / `CLAUDE.md` | Proposition |
|---|---|---|---|
| Pile technique | Next.js + Supabase + Vercel, web/PWA d'abord | Laravel 13 + PostgreSQL/PostGIS + Next.js + Flutter | **Garder la pile actuelle** : les invariants du PDF (contrainte d'exclusion GiST, machines à états, outbox, idempotence) se réalisent en PostgreSQL/Laravel. Supabase/RLS ne sont pas nécessaires. |
| Paiement | PSP, webhooks signés, remboursements | Règle 4 : aucun paiement en ligne au MVP | Modifier la règle 4 **après votre accord** : interface `PaymentGateway` + adaptateur « encaissement confirmé par un admin » d'abord ; PSP réel quand vous l'aurez choisi (compte externe, règle 10). |
| Cible de lancement | Séjours meublés, 20 à 30 unités | Location résidentielle longue durée | Séjours en priorité ; longue durée et vente existantes conservées mais non promues. `rent_furnished_short` existe déjà dans les transactions et le formatage des prix (« / nuit »). |
| Application native | Hors MVP | Application Flutter livrée | Conservée (sunk cost, déjà conforme aux magasins) ; le web reste le canal principal. |
| Identité graphique | « Ne pas inventer de logo alternatif » | Logo fourni intégré (phase 16) | Conforme. |
| Données fictives | Jamais présentées comme réelles | Annonces `[DÉMO]` locales | Conforme ; purge avant toute démonstration publique. |

## Modèle de données à ajouter (Laravel, migrations versionnées)
`units`, `unit_amenities`, `offers` (`stay` seul actif), `rate_plans`, `rate_overrides`, `calendar_blocks`, `booking_holds`, `quotes`, `bookings`, `payment_intents`, `payment_events` (unicité `provider` + `external_event_id`), `refunds`, `payouts` (selon capacités PSP), `support_cases`, `outbox_events`. Existants réutilisés : `properties`, organisations/agences, `audit_logs`, contrôles du Passeport.
Invariants testés : intervalle demi-ouvert `[arrivée, départ)` ; contrainte d'exclusion GiST sur `(unit_id, daterange)` pour les états bloquants ; devis = somme de lignes configurées en XOF entiers ; politique d'annulation figée dans la réservation ; aucune suppression d'une réservation payée.

## Machines à états (transitions par services dédiés, avec audit)
Réservation : `draft → requested → accepted → awaiting_payment → confirmed → checked_in → completed` (+ `declined`, `expired`, `payment_failed`, `cancelled_by_guest`, `cancelled_by_host`, `refunded_*`, `disputed`). Paiement : `created → pending → succeeded | failed | expired`, `refunding → refunded | refund_failed`. Le retour navigateur ne peut jamais marquer un paiement réussi.

## Lots proposés (après validation)
1. **Unités, offres, tarifs, calendrier** (hôte/agence) + tests de non-chevauchement (100 requêtes concurrentes).
2. **Recherche par dates et invités**, fiche d'unité, devis figé.
3. **Demande de réservation → acceptation → verrou temporaire → confirmation**, notifications par outbox.
4. **Paiement** : `PaymentGateway`, adaptateur manuel, journal d'événements idempotent, remboursements tracés ; PSP réel ensuite.
5. **Écrans voyageur « Mes séjours » et hôte « Demandes / Calendrier »** (web puis mobile), dans la charte de la phase 16.
6. **Contrôles différenciés par catégorie** (hébergement touristique : agrément à vérifier par un juriste), administration, rapprochement.

## Décisions attendues de vous
1. Valider **de garder Laravel/Next.js/Flutter** (recommandé) plutôt que de migrer vers Supabase/Vercel.
2. Valider la **modification de la règle 4** (paiement via PSP, d'abord adaptateur manuel).
3. Choisir le **PSP** et ouvrir le compte (hors de mon périmètre), ou me dire de rester sur l'adaptateur manuel.
4. Un **conseil juridique** pour l'agrément des hébergements touristiques et la protection des données (le PDF le demande avant le pilote payant).
