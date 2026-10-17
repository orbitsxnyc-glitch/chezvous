# Phase 15 : rapport (carte, Android, publication, robustesse)

Date : 2026-10-17. Branche `phase-15-carte-mobile` (locale, non poussée). Part de `phase-14-identite-visuelle`.

## 1. Carte des offres (web et mobile)

- **Web** : onglet « Carte » dans la recherche (Leaflet, fonds OpenStreetMap), pastilles de prix, infobulle (photo, titre, prix, quartier), filtres communs avec la liste, mini-carte « Localisation » sur la fiche. **Mobile** : bouton carte/liste dans la recherche (flutter_map), aperçu au toucher, mini-carte sur la fiche.
- **Confidentialité** : positions **approximatives par conception** (zone, jamais l'adresse exacte) ; la mini-carte d'une fiche affiche un cercle de 350 m sauf position exacte choisie par l'annonceur.
- **API** : `GET /listings/map` renvoie maintenant des liens de vignette utilisables (c'était un chemin de stockage) et, **dézoomé, des groupes** (nombre, position moyenne, prix minimal) : sur 8 981 annonces, ≈ 90 ms. Toucher un groupe zoome dessus. 4 tests ajoutés.
- Vérifié dans Chromium (pastilles, groupes, infobulle, 0 violation d'accessibilité) et sur le simulateur iOS avec les vraies tuiles.

## 2. Android (option 1)

- Licences du SDK acceptées sur votre demande ; **APK debug, APK release et bundle release compilés**.
- **Défaut trouvé : la permission INTERNET manquait au manifeste principal** (seuls debug et profile l'avaient) : une version publiée n'aurait pu appeler aucune API. Corrigé et vérifié (`aapt`).
- Signature de release branchée sur `android/key.properties` (non versionné).
- **Non fait** : exécution sur un émulateur ou un appareil (aucune image système installée, ≈ 1,5 Go à télécharger).

## 3. Publication (option 2)

- Icône (iOS et Android adaptatif) et écran de démarrage générés depuis le logo ; icône vérifiée sur le simulateur ; nom d'affichage « ChezVous ».
- **Suppression de compte dans l'application** et **signalement d'annonce** (web et mobile, nouvelle route publique des motifs) : exigés par les boutiques pour les contenus d'utilisateurs.
- Captures aux formats des boutiques (commande documentée), `docs/publication-mobile.md` : prêt / à votre charge / textes de fiche / questionnaires / risques de refus / liste de contrôle.

## 4. Robustesse réseau (option 3)

- Client : erreurs distinctes (hors ligne, lenteur, trop de requêtes, panne serveur) avec messages lisibles, **une nouvelle tentative automatique pour les lectures** (jamais pour les envois, qui pourraient partir en double), délais réglables.
- Écrans : bouton « Réessayer » partout où une liste ou une fiche se charge ; session révoquée : retour à la connexion avec un message unique.
- **Défaut réel trouvé et corrigé** : `setState(() => _future = …)` renvoyait un `Future` (Flutter l'interdit en développement) dans les favoris, les alertes et « Mes annonces ». Les tests de robustesse l'ont révélé.
- 9 tests ajoutés (coupure passagère rejouée, POST jamais rejoué, 503 rejoué mais pas 404, délai dépassé, 429/500, « Réessayer » en recherche et en fiche, session expirée).

## Vérifié

Voir la dernière ligne de `make check` dans le message de livraison. Mobile : 57 tests.

## Limites connues (honnêtes)

- **Fonds de carte OpenStreetMap public : à remplacer avant un lancement public** (règlement d'utilisation). Une requête de tuile révèle l'adresse IP de l'utilisateur au fournisseur.
- Android jamais exécuté ; iOS vérifié sur simulateur seulement ; l'écran de démarrage natif n'a pas pu être capturé.
- Aucun blocage d'utilisateur ; aucune politique de confidentialité ni conditions publiées : **bloquants pour les boutiques**.
- Les captures de boutique utilisent des données fictives.
- Le regroupement de carte utilise une grille simple (cellules fixes) : un groupe peut se couper à la frontière d'une cellule.
