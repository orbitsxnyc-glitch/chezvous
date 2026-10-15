# Phase 10 : rapport (design Figma appliqué à l'application mobile)

Date : 2026-10-15. Branche `phase-10-design-mobile`, locale (non poussée). Référence : `docs/design-figma/` (export Figma Make : `App.tsx`, `index.css`, logo).

## Livré

- **Thème** (`mobile/lib/ui/theme.dart`) : palette du design (vert `#0d4a3e`, sable, terre cuite, états), police **Manrope** embarquée (licence OFL, `assets/fonts/`), coins 8/12/18 px, boutons, champs, cartes, puces, barre de navigation, sélecteur segmenté, feuilles et boîtes de dialogue ; pastille `AppBadge` (succès, attention, neutre, info, accent).
- **Navigation à 5 onglets** comme le design : Accueil, Recherche, Publier, Messages, Compte. Favoris, notifications et alertes passent dans Compte.
- **Accueil** : recherche rapide (location/vente, quartier, budget) qui ouvre la recherche pré-remplie, « À la une » (étiquetée « Mise en avant par son annonceur »), quartiers de Dakar (données de l'API), section « Pourquoi ChezVous ».
- **Écrans restylés** : carte d'annonce, badge de fraîcheur coloré et daté, fiche (faits clés, frais en carte sable avec avertissement, Passeport du Bien avec mention juridique), bulles de messages et cartes de visite, éditeur avec progression en 4 étapes, écran de connexion avec le logo.
- **Logo** : fichier fourni (`assets/brand/`), allégé (640 px) ; icône recadrée pour l'en-tête.

## Défauts trouvés en lançant l'application (invisibles pour les tests) et corrigés

1. **Blocage au démarrage** : `restore()` accédait au trousseau avant l'initialisation du moteur Flutter ; l'application restait sur le chargement. Corrigé (`WidgetsFlutterBinding.ensureInitialized()`), et un trousseau illisible démarre désormais l'application déconnectée.
2. **Requêtes publiques bloquées par un trousseau illisible** : le client lisait le jeton avant toute requête ; une erreur du trousseau empêchait même la recherche. Corrigé : sans jeton lisible, la requête part en anonyme. Tests ajoutés pour les deux cas.

## Vérifié par exécution

- `make check` complet, `flutter analyze` propre, 37 tests Flutter.
- Application **lancée sur le simulateur iPhone 17 Pro** contre l'API locale (données fictives créées puis supprimées) : accueil, quartiers chargés depuis l'API, style conforme (captures examinées).

## Limites connues (honnêtes)

- **Seul l'accueil a été vu à l'écran** : je n'ai pas pu toucher l'interface du simulateur (pas d'outil de clic). Recherche, fiche, messagerie, éditeur, onglets : vérifiés par tests de widgets, **pas visuellement**.
- Ni comparaison pixel à pixel avec Figma, ni test sur Android ou sur un appareil réel ; build du simulateur non signé (le trousseau y est inaccessible, d'où le défaut 2 ci-dessus).
- **Le logo fourni est orange, rouge et jaune, alors que le design est vert et terre cuite** : les deux cohabitent dans l'en-tête (icône orange, nom en noir et terre cuite). Décision de marque à prendre (voir `DECISIONS.md`).
- Mode sombre non fait ; pas de carte (placeholder du design non implémenté) ; pas d'écran « offres » sur mobile.
- L'adresse `localhost` par défaut peut ne pas convenir à tous les simulateurs : utiliser `--dart-define=API_URL=http://127.0.0.1:8000`.
- Le web n'a pas encore reçu le design (prochaine phase).
