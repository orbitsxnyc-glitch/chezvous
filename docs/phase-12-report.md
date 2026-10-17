# Phase 12 : rapport (administration complète)

Date : 2026-10-17. Branche `phase-12-admin`, locale (non poussée). Elle part de `phase-11-design-web` (design inclus).

## Constat de départ

L'API d'administration existait, mais l'interface web n'avait que le tableau de bord, les signalements, les utilisateurs et les envois. Concrètement : **aucune annonce soumise ne pouvait être approuvée sans appel manuel à l'API**, aucun encaissement ne pouvait être confirmé, et **aucun contrôle du Passeport (hors téléphone) ne pouvait être saisi, ni par l'API ni par l'interface**.

## Livré

- **Annonces à modérer** (`/admin/annonces`) : file des plus anciennes, photos, description, approbation, refus avec motif de la liste et message à l'annonceur, lien vers les contrôles du Passeport.
- **Abonnements et règlements** (`/admin/abonnements`) : abonnements et mises en avant, **qui a demandé** (nouveau dans l'API, sinon impossible de rapprocher un paiement), règlements déclarés avec justificatif (lien signé temporaire), confirmer ou rejeter, enregistrer un encaissement reçu, activer / suspendre / rétablir / annuler. L'activation n'est proposée qu'avec un encaissement confirmé ; le serveur la refuse sinon.
- **Vérification des agences** (`/admin/verifications`) : dossier (NINEA, RCCM), documents privés par liens signés de courte durée, approuver ou refuser avec motif.
- **Contrôles du Passeport** (`/admin/passeports`, nouvelle API `GET|POST /admin/passports/{reference}/checks`) : identité, localisation, document, visite terrain ; réussi ou échoué, note interne (obligatoire en cas d'échec), validité facultative, historique complet, audit. Le téléphone et le contrôle professionnel restent automatiques.
- **Offres et tarifs** (`/admin/catalogue`) : formules (prix, quotas, fonctionnalités, activation), mises en avant, coordonnées de règlement ; consultation seule pour un administrateur, modification réservée au super administrateur (déjà imposé par l'API).
- **Référentiels** (`/admin/referentiels`) : quartiers et zones, types de biens, équipements, motifs ; désactivation plutôt que suppression, codes immuables.
- **Modèles de messages** (`/admin/modeles`) : modification par canal, nouvelle version à chaque enregistrement, variables autorisées contrôlées avant envoi.

## Correction de fond

- **Passeport public** : un contrôle échoué ou expiré **masque désormais l'ancien succès** du même type (avant, le dernier *réussi* restait affiché même après un échec ultérieur). Testé.

## Vérifié par exécution

- Backend : Pest **570/570**, PHPStan 0 erreur, Pint. Web : lint, typecheck, Vitest 55/55.
- **Parcours réels contre l'API locale** (comptes et données de test supprimés ensuite) : file de modération (format, refus, approbation interdite après refus), demande d'abonnement → activation refusée sans encaissement → encaissement avec justificatif → activation, saisie d'un contrôle de visite terrain visible sur le Passeport public, lecture du catalogue et refus de modification à un simple administrateur.

## Limites connues (honnêtes)

- **Aucun de ces écrans n'a été vu dans un navigateur par moi** : vérifiés par les types, les appels réels à l'API et des tests unitaires de la logique. Aucun test de bout en bout (Playwright refusé).
- Pas de recherche d'annonce par critères dans l'administration : le Passeport se retrouve par référence ; les annonces déjà publiées ne se modèrent que via les signalements.
- Pas d'envoi de pièces justificatives pour les contrôles du Passeport (volontaire : données personnelles) ; mandats toujours sans écran.
- Pas d'aperçu du rendu d'un modèle de message ; les modèles SMS, WhatsApp et push sont modifiables mais rien n'est envoyé sur ces canaux.
- Les annonces fictives de démonstration restent dans la base locale tant que vous ne demandez pas leur suppression.
