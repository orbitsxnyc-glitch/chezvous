# Publication de l'application mobile (App Store et Google Play)

État au 2026-10-17. Ce document liste ce qui est **prêt**, ce qui **vous revient** (comptes, signatures, textes juridiques) et les **points de vigilance des boutiques**. Rien n'a été envoyé à une boutique.

## 1. Prêt dans le dépôt

| Élément | État |
|---|---|
| Nom affiché | ChezVous (Android et iOS) |
| Identifiant d'application | `sn.chezvous.chezvous` (**définitif une fois publié** : à valider avant le premier envoi) |
| Icône et écran de démarrage | Générés à partir du logo (`mobile/assets/icon`, `flutter pub run flutter_launcher_icons` et `dart run flutter_native_splash:create` pour régénérer) |
| Compilation | Android : APK debug, APK et bundle (`.aab`) release réussis ; iOS : simulateur réussi |
| Permission réseau Android | Ajoutée au manifeste principal (elle manquait : une version publiée n'aurait pu appeler aucune API) |
| Signature Android | Branchée sur `android/key.properties` (ignoré par Git). **Sans ce fichier, la version release est signée avec la clé de debug et la boutique la refusera** |
| Chiffrement iOS | `ITSAppUsesNonExemptEncryption = false` (HTTPS standard uniquement) |
| Suppression de compte dans l'application | Faite (exigée par Apple et Google) |
| Signalement d'une annonce | Fait (exigé par Apple pour les contenus d'utilisateurs : signalement, modération, contact) |
| Captures pour les fiches | Commande ci-dessous (données de démonstration) |

### Captures d'écran des fiches

```bash
cd mobile
STORE_DIR=/chemin/captures flutter test test/screenshots
# crée ios-6.7/ (1290×2796) et android-telephone/ (1080×1920) : accueil, recherche, fiche, messages, espace annonceur
```
Ces images utilisent des données fictives et le moteur de rendu de test. **Pour les boutiques, refaites-les sur un appareil avec de vraies annonces** (les boutiques n'aiment pas les maquettes) ; iPad et 5,5" sont à ajouter si vous ciblez ces formats.

### Compiler

```bash
# Android (bundle pour le Play Store)
flutter build appbundle --release --dart-define=API_URL=https://api.VOTRE-DOMAINE.sn \
  --dart-define=MAP_TILES_URL=https://VOTRE-FOURNISSEUR/{z}/{x}/{y}.png
# iOS (nécessite le compte Apple et la signature dans Xcode)
flutter build ipa --release --dart-define=API_URL=https://api.VOTRE-DOMAINE.sn
```

### Clé de signature Android (à faire une seule fois, à conserver précieusement)

```bash
keytool -genkey -v -keystore ~/chezvous-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```
Créez `mobile/android/key.properties` (jamais dans Git) :
```
storeFile=/chemin/absolu/chezvous-upload.jks
storePassword=…
keyAlias=upload
keyPassword=…
```
**Perdre cette clé = ne plus pouvoir mettre l'application à jour sans procédure de récupération.** Sauvegardez-la hors de l'ordinateur et du dépôt. Google Play gère ensuite la clé de signature définitive (Play App Signing).

## 2. Ce qui vous revient (comptes et frais)

| Besoin | Détail |
|---|---|
| Compte Apple Developer | Programme payant annuel (environ 99 USD) ; identité vérifiée ; certificats et profils créés dans Xcode |
| Compte Google Play Console | Frais uniques (environ 25 USD) ; vérification d'identité. **Les comptes personnels récents doivent mener un test fermé avec un minimum de testeurs pendant une durée imposée avant la production** : vérifiez la règle en vigueur à l'inscription |
| Adresse de support et page de politique de confidentialité | **Obligatoires** (URL publique). La page n'existe pas encore : elle doit être rédigée avec un juriste |
| Conditions d'utilisation | Idem (voir `DECISIONS.md`, questions juridiques) |
| Fournisseur de fonds de carte | OpenStreetMap public est toléré pour un pilote à faible trafic mais **interdit pour un usage massif** (règlement d'utilisation des tuiles). Avant le lancement : fournisseur dédié ou auto-hébergé, branché par `MAP_TILES_URL` (mobile) et `NEXT_PUBLIC_MAP_TILES_URL` (web) |
| Notifications push | Compte Firebase (FCM) et clé APNs Apple ; non branchées |

## 3. Textes de fiche (brouillon à relire)

- **Nom** : ChezVous
- **Sous-titre iOS (30)** : Logements vérifiés à Dakar
- **Description courte Android (80)** : Trouvez un logement réellement disponible à Dakar, avec son Passeport.
- **Description longue** :
  > ChezVous vous aide à trouver, vérifier et visiter un logement à Dakar sans perdre de temps sur des annonces périmées.
  > • Une disponibilité datée : chaque annonce indique quand son annonceur l'a confirmée.
  > • Le Passeport du Bien : des contrôles explicites et datés. Un contrôle ne garantit pas la propriété juridique du bien.
  > • Une carte pour localiser les offres, des filtres par quartier, budget et chambres.
  > • Contact par la plateforme : les coordonnées de l'annonceur restent privées, vous échangez par messages et proposez des visites.
  > • Alertes sur vos recherches, favoris, notifications.
  > Annonceurs : publiez vos biens avec photos, confirmez leur disponibilité en un geste, suivez vos demandes.
- **Mots-clés iOS (100)** : location,logement,appartement,villa,Dakar,Sénégal,immobilier,maison,studio,annonce
- **Catégorie** : Immobilier (Play), Style de vie ou Services (App Store) ; **classification d'âge** : à remplir (contenus d'utilisateurs, pas de jeu d'argent)

## 4. Questionnaires des boutiques (réponses préparées)

**Données collectées** (Play « Sécurité des données » et App Store « Confidentialité ») :
| Donnée | Collectée | Usage | Partagée |
|---|---|---|---|
| Nom, e-mail, téléphone | Oui (compte) | Fonctionnement, sécurité | Non |
| Photos (annonces) | Oui, choisies par l'utilisateur dans sa photothèque | Fonctionnement | Visibles publiquement dans l'annonce |
| Messages | Oui | Fonctionnement | Entre les deux parties |
| Position de l'appareil | **Non** (la carte n'utilise pas le GPS) | — | — |
| Identifiants publicitaires, suivi | Non | — | — |
Chiffrement en transit : oui (HTTPS). Suppression : dans l'application (Compte) et par demande.

## 5. Risques de refus à connaître

1. **Politique de confidentialité absente** : refus immédiat.
2. **Contenu d'utilisateurs** : Apple exige signalement, modération, blocage des abus et contact. Signalement et modération existent ; **le blocage d'un utilisateur n'existe pas** (à ajouter si Apple le demande) ; l'adresse de support est à fournir.
3. **Compte de démonstration** : Apple demande un identifiant de test pour les écrans protégés (créer un compte dédié, sans données réelles).
4. **Vérification du numéro** : tant que les codes partent par e-mail, le numéro n'est pas prouvé (voir phase 13) ; ne pas prétendre le contraire dans la fiche.
5. **Version Android de test** : les captures et le comportement sur Android n'ont été vérifiés que par compilation et tests, pas sur un appareil.

## 6. Liste de contrôle avant le premier envoi

- [ ] Identifiant `sn.chezvous.chezvous` confirmé
- [ ] API de production en HTTPS, `API_URL` réglé, certificat valide
- [ ] Fournisseur de fonds de carte choisi (`MAP_TILES_URL`)
- [ ] Clé de signature Android créée et sauvegardée ; comptes Apple et Google ouverts
- [ ] Politique de confidentialité et conditions publiées (URL), adresse de support
- [ ] Captures réelles, textes validés, classification d'âge
- [ ] Test sur au moins un vrai iPhone et un vrai Android (réseau lent, hors connexion, session expirée)
- [ ] Compte de démonstration pour les relecteurs
