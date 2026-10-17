# Phase 14 : rapport (identité du logo, revue visuelle, résultats et fiche, charge, accessibilité)

Date : 2026-10-17. Branche `phase-14-identite-visuelle`, locale (non poussée). Elle part de `phase-13-email-otp`.

## 1. Identité visuelle tirée du logo (décision du porteur)

- Couleurs mesurées sur le logo : orange `#F66F11`, rouge `#F13B3A`, jaune `#FBB413`. Le vert du design Figma est abandonné.
- **Orange et rouge du logo sont trop clairs pour du texte ou un bouton (blanc dessus : 2,9 et 3,9 contre 4,5 requis).** Ils servent au décor et au mot-symbole (grand texte gras). Les couleurs d'interface sont dérivées et contrastées : orange brûlé `#C2410C` (boutons, liens, 5,2 avec le blanc), `#9A3412` (survol, sections), fonds sable chaud.
- Appliqué au web (jetons `globals.css`) et au mobile (`AppColors`). **Tests de contraste** (Vitest et Flutter) : une palette qui dégrade la lisibilité fait échouer la CI.

## 2. Revue visuelle écran par écran

- **Mobile** : 15 écrans rendus en images (test `test/screenshots`, `SHOTS_DIR=… flutter test test/screenshots`, ignoré en CI), avec les vraies polices et icônes. Défauts trouvés et corrigés :
  - **la police Manrope n'était appliquée qu'à une partie du texte** (styles redéfinis sans police : retour à la police du système) ;
  - deux listes déroulantes de l'accueil débordaient sur écran étroit ; carte « À la une » trop courte ;
  - filtres de recherche peu lisibles (désormais en pastilles) ; « négociable » faisait passer le prix sur deux lignes ;
  - « Appartement » coupé en deux mots dans la fiche ; « Mes annonces » affichait un nom technique de champ (`cover_image`) et un texte devenu faux (« sur le site »).
- **Web** : 20 captures (bureau et mobile) dans Chromium via `infra/scripts/web-audit.mjs`. Défauts trouvés et corrigés :
  - **l'API publique ne renvoyait que le code technique du type de bien** (« warehouse ») : ajout de `property_type_label`, utilisé par le web et le mobile (test ajouté) ;
  - icône d'image cassée quand une photo est introuvable (composant `SafeImage`, y compris pour une image déjà en échec avant l'hydratation) ;
  - en-tête mobile sur trois lignes ; avertissement de rendu (heure lue au pré-rendu) sur la recherche et la fiche ;
  - mention « téléphone vérifié » affichée à tort pour un annonceur vérifié par e-mail (oubli de la phase 13) ;
  - libellé de tri tronqué.

## 3. Pages de résultats et fiche d'annonce (web)

- **Résultats** : panneau de filtres latéral fixe (repliable sur mobile), cartes en lignes compactes, compteur, état vide explicite, fil d'Ariane.
- **Fiche** : mosaïque de photos, faits clés, description, équipements, frais, Passeport, colonne droite fixe (annonceur et contact), annonces similaires, fil d'Ariane.

## 4. Test de charge de la recherche (10 000 annonces fictives, PostgreSQL 17 local)

- **Requêtes (commande `search:benchmark`)** : p95 entre 9 et 52 ms (liste 16 ms, filtres combinés 17 ms, carte 52 ms) ; cible de 400 ms largement tenue.
- **HTTP (script `infra/scripts/load-test.mjs`, serveur PHP intégré à 8 processus sur un portable, mode non optimisé)** : ~28 req/s par processus ; ~145 req/s à 10 utilisateurs simultanés (p95 114 ms), p95 247 ms à 25, 453 ms à 50 (saturation des 8 processus). Aucune erreur.
- **Un premier essai donnait des chiffres 100 fois meilleurs : c'était une erreur PHP renvoyée avec le code 200.** Le script vérifie désormais le corps JSON de chaque réponse.
- Limites : mesure sur un poste de développement avec d'autres programmes actifs ; l'essai avec cache de configuration et OPcache n'a rien amélioré de façon mesurable ici. À refaire sur l'hébergement réel avec PHP-FPM.

## 5. Audit d'accessibilité (axe-core, WCAG 2.1 AA, contrastes réels)

- **0 violation sur 20 pages** (10 pages × bureau et mobile : accueil, recherche, recherche filtrée, page de quartier, connexion, inscription, offres, fiche, plus les écrans protégés dans leur état déconnecté), aucun défilement horizontal. Deux défauts mineurs trouvés puis corrigés : ordre des titres (h1 puis h3) et repère `aside` imbriqué.

## Limites connues (honnêtes)

- L'audit automatique ne couvre qu'une partie des critères d'accessibilité (en général 30 à 40 %) : pas de test avec lecteur d'écran, de navigation clavier complète, ni de zoom à 200 %.
- **Les écrans réservés aux comptes connectés (administration, éditeur, messagerie) n'ont été audités que dans leur état de redirection**, pas connectés.
- Le mobile n'a pas été comparé visuellement à un vrai iPhone dans cette phase (images produites par le moteur de test Flutter).
- Les photos de démonstration n'existent pas (l'état « Photo indisponible » est celui qui a été vérifié) ; la galerie avec plusieurs vraies photos n'a pas été vue.
- Chromium (≈250 Mo) a été installé hors du dépôt (`/tmp/pw`), pour l'audit uniquement.
