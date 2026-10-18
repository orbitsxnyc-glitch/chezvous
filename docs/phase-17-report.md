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

## Lot 4 — Paiements et remboursements (livré)
- **Interface** `PaymentGateway` (règle 4) + `PaymentGatewayManager` (config `payments.php`). Seul adaptateur actif : `ManualGateway` (encaissement manuel confirmé par un administrateur). Aucun appel à un tiers. Wave et Orange Money = un adaptateur de plus quand le compte marchand existera (signature et relecture auprès du prestataire à écrire à ce moment-là).
- **Tables** : `payment_intents` (un règlement en cours et un seul règlement réussi par séjour, garantis par index uniques), `payment_events` (**unique `(prestataire, identifiant externe)`** : un événement rejoué ne fait rien), `refunds`.
- **Flux** : séjour accepté → le voyageur prépare le règlement (référence `CV-…` et consignes tirées des réglages `payment_instructions`) → il paie hors plateforme et **déclare** (information seulement) → un administrateur confirme le montant reçu → le séjour devient confirmé (verrou → confirmé) via l'unique chemin `applyVerifiedEvent`, aussi destiné aux futurs webhooks.
- **Garde-fous** : le retour du voyageur ne confirme jamais ; montant ou devise différents = événement mis en quarantaine, règlement inchangé, confirmation correcte toujours possible ensuite ; paiement reçu après expiration = règlement marqué réussi mais séjour non confirmé et remboursement intégral ouvert ; règlement rejeté = le voyageur peut en préparer un autre tant que le délai court.
- **Remboursements** : annulation par l'hôte = 100 % ; par le voyageur = palier de la politique **figée à la demande** (jamais la politique courante) ; ils restent `requested` jusqu'à confirmation d'un administrateur (jamais supposés payés), partiel possible, échec possible. Toutes les actions sont auditées.
- **Webhooks** : route `POST /api/v1/webhooks/payments/{provider}` présente mais refusée (404) tant qu'aucun adaptateur ne la prend en charge.
- **Tests** : 21 nouveaux.

## Lot 5 — Écrans (web et mobile livrés)
- **Voyageur** : `/sejours` (recherche par dates, voyageurs, quartier, budget ; prix total par logement), `/sejours/[id]` (fiche, calendrier de dates, prix détaillé calculé par le serveur, demande sans débit), `/mes-sejours` et `/mes-sejours/[id]` (état en toutes lettres, prix, politique d'annulation, règlement : référence et consignes, « J'ai payé » qui ne confirme rien, historique, annulation).
- **Hôte** : `/hote` (logements, création), `/hote/unites/[id]` (publication, photos, tarif de base, tarifs exceptionnels, dates fermées), `/hote/demandes` (accepter, refuser, annuler avec motif, échéances affichées).
- **Administration** : `/admin/sejours` (logements à valider, règlements à vérifier avec confirmation du montant réellement reçu, remboursements à effectuer).
- **API** : ajout, couverture et suppression de photos d'un logement (sans photo prête, aucune validation possible).
- **Qualité** : audit axe-core sur 26 pages (dont séjours et fiche d'unité) : 0 violation ; contraste du texte « Pas de photo » corrigé. Vitest 79.
- **Mobile (Flutter)** : recherche de séjours (calendrier en français, voyageurs, prix total) depuis une carte de l'accueil ; fiche du logement avec prix détaillé et demande sans débit (clé d'idempotence par intention) ; « Mes séjours » (liste et détail : échéances, règlement avec référence et consignes, « J'ai payé » qui ne confirme rien, annulation) ; « Demandes de séjour » côté hôte (accepter, refuser, annuler avec motif), le tout dans « Mon espace ». La gestion des logements (tarifs, photos, calendrier) reste sur le site. 6 tests Flutter ajoutés (63 au total).

## À venir
5. Écrans voyageur / hôte (web puis mobile).
6. Contrôles par catégorie, administration, rapprochement.
