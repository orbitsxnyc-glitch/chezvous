# Phase 5 : rapport (demandes, messagerie, visites, favoris, alertes)

Date : 2026-10-18. Branche `phase-5-demandes`, locale.

## Livré

- **Demandes (DEM-01, 02, 06) :** `POST /listings/{id}/inquiries`, fil de messages, statuts (nouvelle, en cours, visite planifiée, conclue, perdue, fermée) via un service unique avec audit ; une seule demande ouverte par demandeur et par annonce ; interdiction de contacter sa propre annonce (ou celle de son agence) ; limite de débit.
- **Confidentialité :** le téléphone du demandeur n'est visible que du demandeur et du côté annonceur autorisé (annonceur ; gérant ; agent attribué ; lecture seule pour un agent à droit étendu) ; le téléphone de l'annonceur n'est jamais exposé ; les notifications ne contiennent aucune coordonnée ; les tiers reçoivent 404.
- **Notes internes (DEM-05) :** côté annonceur uniquement ; le demandeur ne sait pas qu'elles existent (404).
- **Attribution (AGC-04) :** les demandes héritent de l'agent de l'annonce ; réattribution par le gérant ; suspension d'un agent = demandes et annonces réattribuées au gérant.
- **Visites (DEM-03, 04) :** 1 à 3 créneaux, confirmation par l'autre partie, contre-proposition, report, annulation, « effectuée » (annonceur), retour du demandeur ; aucune double réservation (même bien, agenda de l'annonceur, agenda du demandeur) ; heures en UTC.
- **Rappels :** visites à 24 h et 2 h, aux deux parties, une seule fois (réservation atomique) ; relance après 48 h sans réponse ; taux de réponse d'agence.
- **Favoris (FAV-01) et alertes (FAV-02) :** baisse de prix, indisponibilité (réarmée si l'annonce revient) ; recherches enregistrées avec alertes regroupées, plafonnées, sans doublon.
- **Notifications :** liste, marquage lu, compteur (dans l'application).
- **Web :** contact, demande de visite et favoris sur la fiche ; `/demandes`, `/demandes/{id}`, `/boite`, `/favoris`, `/alertes`, `/notifications` ; bouton « M'alerter » dans la recherche.
- **Statistiques d'agence :** demandes reçues, visites à venir et taux de réponse remplacent les `null`.

## Vérifié par exécution

- Pest 341/341, Vitest 37/37, PHPStan 0 erreur, lint, typecheck et build du web.
- Parcours réel avec la vraie API et de vrais cookies de session : demande, réponse, proposition et confirmation de visite, notifications, favori ; le chercheur ne voit pas le téléphone de l'annonceur, un tiers reçoit 404, le demandeur ne peut pas confirmer sa propre proposition.

## Limites connues

- Aucun envoi réel : rappels, relances et alertes sont des notifications dans l'application (Phase 7). Une visite peut donc être oubliée par quelqu'un qui n'ouvre pas l'application.
- Les tâches planifiées exigent `php artisan schedule:work` (ou un cron) ; non testé en conditions réelles prolongées.
- Les boutons « Appeler » et « WhatsApp » de la spec (RCH-04) ne sont pas fournis : le contact passe par la demande.
- Pas de pièces jointes, pas de signalement d'un message, pas de blocage d'un utilisateur.
- Pas de notification « en temps réel » (il faut rafraîchir) ; pas de compteur de non lues dans l'en-tête.
- Le retour du demandeur après visite n'alimente pas encore la fraîcheur du Passeport (information affichée à l'annonceur seulement).
- Aucun test dans un navigateur des nouveaux écrans ; les conflits de réservation concurrents sont verrouillés en base mais non testés en parallèle.
- Conservation et finalité des données de contact : avis juridique (CDP) à obtenir.
