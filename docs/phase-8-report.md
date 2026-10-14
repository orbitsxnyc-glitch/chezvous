# Phase 8 : rapport (application mobile Flutter)

Date : 2026-10-14. Branche `phase-8-mobile`, locale (non poussée).

## Livré

- **Socle :** client API (jeton d'appareil Sanctum révocable, erreurs normalisées, coupure réseau lisible), jeton stocké dans le trousseau iOS / Keystore Android (`flutter_secure_storage`), session restaurée au démarrage (jeton refusé = effacé ; panne réseau = jeton conservé), déconnexion automatique si le jeton est révoqué.
- **Compte :** connexion, inscription (particulier ou agence), vérification du téléphone par code, renvoi du code.
- **Recherche publique (sans connexion) :** filtres (location/vente, quartier, chambres, budget), pagination par curseur, protection contre les réponses tardives d'un filtre précédent, badge de fraîcheur daté.
- **Fiche d'annonce :** photos, frais d'entrée **tels que saisis par l'annonceur** avec leur avertissement (aucun total calculé), Passeport du Bien (contrôles explicites et datés, mention « un contrôle ne garantit pas la propriété juridique »), favoris.
- **Alertes :** création depuis la recherche, liste, suppression.
- **Contact et messagerie :** premier contact par la plateforme (le téléphone de l'annonceur n'est jamais affiché), conversations, messages, propositions et acceptation de créneaux de visite, annulation, notifications dans l'application.
- **Annonceur (minimal) :** « Mes annonces » avec statut, confirmation de disponibilité (alimente la fraîcheur), pause, reprise, clôture avec confirmation.
- **Navigation :** Recherche, Favoris, Messages, Compte ; les pages privées demandent une connexion et sont reconstruites à chaque changement de compte.

## Vérifié par exécution

- `flutter analyze` sans problème, `flutter test` 29/29.
- `flutter build ios --simulator --debug` réussit.
- Lecture de **vraies réponses de l'API locale** (15 annonces fictives créées puis supprimées) : liste, fiche (images, frais, contrôles), quartiers lus correctement.

## Limites connues (honnêtes)

- **Application jamais lancée visuellement** : ni sur simulateur ni sur appareil ; aucune capture d'écran. Le design Figma n'est pas appliqué (thème sobre).
- **Android non testé** : licences du SDK non acceptées, aucune compilation Android.
- **Création et édition d'annonce : ajoutées en Phase 9** (`docs/phase-9-report.md`). L'abonnement reste non fait sur mobile.
- **Notifications push : non branchées.** Aucun compte Firebase, aucun jeton n'est enregistré. L'API accepte déjà les appareils (`POST /my/devices`).
- **Pas de carte** (fournisseur non choisi, question 9), pas de zones de quartier plus fines que la liste.
- **Pas de tests contre une API réelle dans la CI** : les tests utilisent une API simulée ; seule la lecture des modèles a été vérifiée à la main.
- Pas de mode hors ligne, pas de traduction (français uniquement).
- Les fichiers iOS générés par CocoaPods (`Podfile`, `Podfile.lock`, projet Xcode) sont versionnés.
