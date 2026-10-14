# Phase 9 : rapport (création d'annonce avec photos)

Date : 2026-10-14. Branche `phase-9-creation-annonce`, locale (non poussée). **Aucun changement de l'API** : les écrans utilisent les routes existantes.

## Livré

- **Web** (`/mes-annonces`, `/mes-annonces/nouvelle`, `/mes-annonces/[id]`) :
  - liste des annonces avec statut et motif de refus ;
  - création du brouillon (type de bien, location ou vente) ;
  - éditeur en 4 sections : l'annonce (titre, description, prix, frais saisis), le bien (quartier, adresse privée, surface, pièces, équipements), photos (envoi multiple, couverture, suppression, suivi du traitement), publication (champs manquants nommés en français, soumission en modération) ;
  - lien depuis l'accueil, page exclue de `robots`.
- **Mobile** : les mêmes sections dans l'application (création, édition, photos via la photothèque avec réduction à 2000 px / qualité 85 avant envoi, soumission), accessibles depuis Compte > Mes annonces (bouton +). Texte d'autorisation photothèque iOS ajouté.

## Vérifié par exécution

- Web : lint, typecheck, Vitest 48/48, `next build`.
- Mobile : `flutter analyze` propre, 34 tests, compilation iOS simulateur réussie.
- **Parcours réel contre l'API locale** (utilisateur temporaire, supprimé ensuite ainsi que ses fichiers) : création, mise à jour des champs, `missing_fields = [cover_image]`, envoi d'une photo avec le champ `images[]` (traitée en « prête » et marquée couverture après exécution du worker), `missing_fields = []`, soumission → `pending_moderation`.

## Écarts avec le plan annoncé

- **Pas d'enregistrement automatique** : chaque section s'enregistre par un bouton explicite (plus simple et sans risque d'écraser une saisie). À reprendre avec le design.
- Pas de réordonnancement des photos par glisser-déposer (l'API le permet, pas d'écran).

## Limites connues (honnêtes)

- **Écrans jamais vus dans un navigateur ni sur simulateur** (pas de Playwright, application mobile non lancée visuellement) : seuls les appels, les données et la compilation sont vérifiés.
- Le worker de file doit tourner (`php artisan queue:work`) sinon les photos restent « en traitement ».
- Aucune carte : la localisation passe par le quartier (liste), pas de position GPS.
- Quotas de formule : l'API refuse et le message d'erreur s'affiche tel quel, sans lien vers les offres.
- Les agences (agents, attribution) n'ont pas d'écran dédié ; le comportement dépend des droits renvoyés par l'API.
- Pas de brouillon hors ligne ; l'envoi de photos nécessite une connexion stable (délai de 2 minutes).
