# Phase 17 — Séjours (en cours)

Branche `phase-17-sejours`. Plan et écarts : `docs/phase-17-plan-sejours.md`. Décisions du porteur (18/10/2026) : garder Laravel ; règle 4 modifiée (interface `PaymentGateway`, encaissement manuel d'abord, Wave / Orange Money ensuite sur compte marchand ouvert par le porteur).

## Lot 1 — Unités, tarifs, calendrier (livré)
- **Tables** : `units`, `rate_plans` (un plan par unité, XOF entiers), `rate_overrides` (intervalle demi-ouvert, priorité), `calendar_blocks`. Contraintes SQL : dates cohérentes, devise XOF, statuts valides.
- **Services** (`app/Services/Stays`) : `UnitService` (création, tarif, exceptions de prix, transitions d'état avec audit — une unité n'est activée qu'avec un tarif), `CalendarService` (fermetures, détection de conflit), `PricingService` (prix par nuit, priorité des exceptions, ménage, durées min/max), `StayDates` (intervalle `[arrivée, départ)`).
- **Policy** `UnitPolicy` : propriétaire, gérant d'agence, agent attribué ; tout le reste en 404.
- **API hôte** `/api/v1/host/units` : liste, création, fiche, modification, tarif, exceptions, statut, fermetures, aperçu prix + disponibilité.
- **Configuration** : durées maximales dans `config/chezvous.php` (`stays.*`), aucune valeur en dur.
- **Tests** : 19 (calcul de prix, chevauchements aux bornes, limites configurables, transitions, droits inter-comptes, agence).

## Lot 2 — Recherche, fiche publique, devis figé (livré)
- **Publication** : une unité n'est publique que `active` ET validée par la modération (`approved_at`) ; la validation exige tarif, quartier et au moins une photo prête ; file et décisions dans `/api/v1/admin/units/*`, tracées dans `audit_logs`. Ni le statut ni l'approbation ne se modifient par assignation de masse.
- **API publique** : `GET /api/v1/stays` (dates, voyageurs, quartier, budget ; exclut les unités fermées ou hors durées) et `GET /api/v1/stays/{id}` (disponibilité, détail du prix ou raison du refus). Aucune adresse exacte, aucun propriétaire, téléphone ni e-mail.
- **Devis** : `POST /api/v1/quotes` (compte requis) revalide côté serveur (publication, capacité, fenêtre de réservation, disponibilité, durées), puis fige les lignes : hébergement, ménage, frais de service, taxes. Montants entiers en XOF (arrondi au XOF le plus proche), total = somme exacte des lignes, expiration configurable, snapshot des règles. Lisible seulement par son voyageur.
- **Frais et taxes** : réglage en base `stay_fees` (`service_fee_bps`, taxes en % ou par nuit) ; repli sur la configuration (0 par défaut). Les taux légaux restent à valider par un conseil juridique avant activation.
- **Tests** : 18 nouveaux (recherche, fuite de données, devis figé, arrondis, expiration, 409 sur dates fermées, droits, validation).

## Lot 3 — Demande, acceptation, verrou, confirmation (livré)
- **Tables** : `bookings` (un devis = une demande), `unit_occupancies` (**contrainte d'exclusion GiST** `unit_id` + `daterange(check_in, check_out, '[)')` pour les occupations `held` / `confirmed` / `checked_in`), `booking_events` (historique des transitions avec acteur et raison), `outbox_events`.
- **Principe** : une demande ne bloque aucune date ; l'acceptation pose un verrou temporaire (durée configurable) dans une transaction qui verrouille l'unité ; en cas de conflit, la base refuse et l'API répond 409 `unavailable`. Départ et arrivée le même jour sont compatibles.
- **Machine à états** (`BookingService`, tableau de transitions) : `requested → accepted → (awaiting_payment) → confirmed`, plus `declined`, `expired`, `cancelled_by_guest`, `cancelled_by_host`. La confirmation (`confirm()`) n'est appelable que par le futur service de paiement : aucune route API ne la propose.
- **Expiration** : `stays:run --expire` (chaque minute) ; en plus, un verrou échu mais pas encore nettoyé ne bloque jamais une nouvelle acceptation (contrôle sur `expires_at`).
- **Idempotence** : même clé = même demande (200), jamais de doublon ; un devis ne sert qu'une fois ; pas de demande en double sur des dates qui se chevauchent pour la même unité.
- **Notifications** : événements écrits dans la même transaction (outbox), diffusés par `stays:run --outbox` avec reprises (plafond configurable) ; aucune coordonnée personnelle. Six types ajoutés au catalogue et aux modèles.
- **Vie privée** : l'hôte ne voit que le prénom du voyageur.
- **Politique d'annulation** : figée dans la réservation à la demande (valeurs de repli `flexible` / `moderate` / `strict` dans la configuration, à valider commercialement) ; le calcul de remboursement viendra avec les paiements.
- **Tests** : 43 nouveaux (flux, bornes de dates, contrainte SQL, expirations, outbox, droits inter-comptes, agence). **Contrôle de concurrence réel** : `php artisan stays:concurrency-check --n=50` lance 50 processus qui acceptent en parallèle 50 demandes sur les mêmes nuits : 1 acceptée, 49 refusées (409), 1 seul verrou (outil de développement, refuse de tourner en production).

## À venir
4. `PaymentGateway`, adaptateur manuel, journal d'événements idempotent, remboursements.
5. Écrans voyageur / hôte (web puis mobile).
6. Contrôles par catégorie, administration, rapprochement.
