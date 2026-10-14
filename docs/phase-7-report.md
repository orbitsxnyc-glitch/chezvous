# Phase 7 : rapport (notifications, administration, durcissement)

Date : 2026-10-14. Branche `phase-7-notifications-admin`, locale (non poussée).

## Livré

- **Notifications (NOT-) :** `NotificationDispatcher` (notification interne toujours créée, puis un envoi par canal via une file, 3 tentatives puis « échec »), catalogue de 31 types dans `config/notifications.php`, gabarits versionnés en base avec variables autorisées (aucune donnée personnelle), préférences par type et par canal (les types de sécurité sont obligatoires), journal des envois et relance manuelle.
  - **Canaux :** l'**e-mail est le seul canal réel**. SMS, WhatsApp et push sont des **simulations honnêtes** : statut « stubbed », jamais comptés comme remis.
- **Administration (ADM-) :** signalements (masquer, avertir, suspendre l'annonceur, classer), utilisateurs (recherche, suspension, réactivation, rôle administrateur), agences, référentiels, tableau de bord (revenu confirmé distinct du déclaré), journal d'audit. Un utilisateur suspendu perd l'accès immédiatement et ses annonces de particulier sont masquées.
- **Double authentification :** TOTP obligatoire pour les administrateurs, 8 codes de secours, protection contre le rejeu, vérification liée à la session ou au jeton, réinitialisation par un autre super administrateur (`chezvous:reset-2fa` en dernier recours). Le rôle est vérifié **avant** la liaison des modèles : un non-administrateur reçoit 403 que l'identifiant existe ou non.
- **Durcissement :** en-têtes de sécurité API et web, `private, no-store` pour les réponses authentifiées, journaux JSON masqués (e-mails, téléphones, jetons, clés sensibles), identifiant de requête, nettoyage Sentry (désactivé sans DSN), Horizon réservé au super administrateur vérifié, commande `chezvous:preflight` (échoue sur toute configuration de production dangereuse), test qui parcourt **toutes** les routes `api/v1` (401 sans connexion, 403 pour les routes d'administration).
- **Exploitation :** `infra/scripts/backup.sh` (chiffré AES-256, rotation) et `restore.sh` (refuse une base non vide), `npm audit` dans la CI.
- **Web :** `/admin` (noindex) avec activation et saisie du second facteur, tableau de bord, signalements, utilisateurs, envois de notifications.

## Vérifié par exécution

- Pest 563/563, PHPStan 0 erreur, Pint, Vitest 45/45, lint, typecheck, `next build`, `composer audit` et `npm audit` sans vulnérabilité.
- Sauvegarde puis restauration réelles dans une base temporaire : mêmes nombres de lignes (base de développement quasi vide, donc test de principe).

## Défauts trouvés par les tests et corrigés

- Existence d'identifiants révélée aux non-administrateurs (404 au lieu de 403) : rôle vérifié avant la liaison.
- En-têtes de sécurité absents des 401 et 404 : middlewares passés en global.
- Numéros locaux à 9 chiffres non masqués dans les journaux.
- Un test modifiait la connexion par défaut et laissait une transaction ouverte, bloquant la base de test.

## Limites connues (honnêtes)

- **Non fait : tests de bout en bout Playwright** (lot F), sur décision du porteur (installation d'environ 1 Go). Les parcours n'ont été vérifiés que par les tests de l'API et les tests unitaires web.
- **Interface `/admin` non essayée dans un navigateur** et incomplète : pas encore d'écran pour abonnements et paiements, vérifications d'agence, référentiels, modèles de notification (leurs routes API existent).
- **Pas de CSP stricte sur le web** (nonce à prévoir avec l'hébergement définitif).
- SMS, WhatsApp et push n'envoient rien ; le fournisseur reste à choisir (questions 5 à 7).
- La restauration n'a pas été testée sur un volume réel ni vers un stockage externe.
- Les 2 routes de téléchargement signé sont exclues du test de rôle (l'identifiant factice répond 404 avant la signature).
