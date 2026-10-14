# Phase 6 : rapport (offre commerciale sans paiement en ligne)

Date : 2026-10-19. Branche `phase-6-offre-commerciale`, locale.

## Livré

- **Catalogue en base (COM-01, 02) :** `plans`, `premium_products`, `settings`. Formules gratuites actives ; formules payantes et options créées **inactives**, prix et quotas « à fixer ». Une formule n'est proposée que si elle est entièrement configurée ; seul le super administrateur règle le catalogue et les coordonnées de règlement.
- **Demande (COM-03) :** souscription « en attente » avec référence `PAY-AAAA-NNNNNN`, prix et quotas figés (`plan_snapshot`), instructions de règlement hors ligne ; aucun avantage accordé. Réservée aux particuliers et aux gérants.
- **Règlement et activation (COM-04) :** règlements déclarés (annonceur) puis confirmés (administrateur) ; l'administrateur peut enregistrer un encaissement confirmé avec justificatif privé (lien signé de 5 minutes). Activation uniquement avec un encaissement confirmé couvrant le prix.
- **Droits (COM-05) :** `EntitlementService` (annonces actives, agents, photos par annonce, crédits) ; `QuotaService`, les photos et les invitations d'agents en dépendent ; retour automatique au gratuit.
- **Expiration (COM-06) :** tâches planifiées, rappels à 7 et 1 jour, délai de grâce, choix des annonces à garder, mise en pause des plus anciennes, jamais de suppression.
- **Historique (COM-07) :** `audit_logs` avec auteur et date, endpoint d'historique.
- **Mises en avant :** « Remontée » (tri), « À la une » (plafond, rotation), crédits inclus, suspension quand l'annonce quitte l'état publié.
- **Web :** `/offres` (public), `/abonnement` (droits, demande, règlement, mises en avant, choix des annonces à garder), « À la une » sur l'accueil.

## Vérifié par exécution

- Pest 411/411, Vitest 43/43, PHPStan 0 erreur, lint, typecheck, build sans API.
- Parcours réel (API et vraies sessions) : catalogue masqué puis réglé, demande, activation refusée sans encaissement (y compris après une déclaration du client), encaissement confirmé puis activation, droits relevés, « Remontée » en tête de la pertinence et sans effet sur le tri par date.

## Défauts trouvés par les tests et corrigés

- Activation : la nouvelle souscription était active avant que l'ancienne soit marquée remplacée (violation d'unicité en base).
- Rappels : une souscription expirant dans moins d'un jour recevait aussi le rappel de 7 jours.
- Plafond d'agents : une invitation ancienne pouvait dépasser le plafond à l'acceptation.

## Limites connues

- **Aucun écran d'administration** des encaissements et activations : l'API est complète, l'interface vient en Phase 7.
- Les **fonctionnalités de formule** (`advanced_stats`, `badge`, `enriched_agency_page`) sont enregistrées mais non appliquées.
- Aucun prix, quota ni coordonnée de règlement n'est fixé : tout est à renseigner par le super administrateur.
- Les annonces en modération au-delà du quota ne peuvent pas être mises en pause automatiquement.
- Une annonce « Remontée » périmée (information ancienne) reste en tête : le badge de fraîcheur la signale mais le classement ne la rétrograde pas.
- Les tâches planifiées exigent `php artisan schedule:work` ou un cron ; non testé en fonctionnement prolongé.
- Conditions générales de vente, facturation, TVA et validation juridique de l'offre : hors périmètre, à obtenir.
