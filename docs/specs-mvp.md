# ChezVous — Spécifications fonctionnelles MVP

Oct 14, 2026 · @Elimane BA

## 1. Objet et conventions

Ce document traduit le cahier des charges v1.1 en spécifications exécutables pour le MVP de ChezVous, puis en 9 phases de développement confiées à Claude Code. Il sert de référence unique pour le backend Laravel, le web Next.js et l'application Flutter.

Chaque règle porte l'un de ces trois statuts :

| Statut | Signification | Conséquence pour le développement |
| --- | --- | --- |
| **Décision** | Validé par le porteur du projet | Implémenté tel quel |
| **Hypothèse de travail** | Choix par défaut pour avancer, réversible | Implémenté de façon paramétrable, sans valeur codée en dur |
| **À arbitrer** | Bloque une phase précise | La phase concernée attend la réponse |

Règle générale : tout prix, quota, durée ou seuil est stocké en base ou en configuration, jamais dans le code. Les arbitrages commerciaux pourront ainsi changer sans redéploiement.

## 2. Hypothèses de travail retenues par défaut

Pour démarrer sans attendre les arbitrages commerciaux, les choix ci-dessous sont appliqués. Tous sont réversibles et pilotés par la configuration.

| # | Sujet | Hypothèse de travail | Pourquoi ce choix |
| --- | --- | --- | --- |
| H1 | Ordre de livraison | Web responsive d'abord (phases 1 à 7), application Flutter ensuite (phase 8) sur la même API | Une seule source de règles métier, recette plus rapide |
| H2 | Activation commerciale | Demande de souscription par l'annonceur, puis activation manuelle par un administrateur après encaissement hors ligne (Wave, Orange Money, virement, espèces) | Aucun paiement en ligne au MVP |
| H3 | Devise | Franc CFA (XOF), montants entiers sans décimales | Marché sénégalais |
| H4 | Formules | 4 formules de départ : Gratuit, Pro Starter, Pro Business, Pro Premium ; quotas et prix laissés à 0 jusqu'à arbitrage | Structure prête, valeurs à fixer |
| H5 | Mise en avant | Options « À la une » (page d'accueil) et « Remontée » (haut des résultats), durées de 7, 15 ou 30 jours | Durées usuelles des portails immobiliers |
| H6 | Particuliers | Quota gratuit configurable d'annonces actives simultanées (valeur initiale : 3) | Limiter les abus sans freiner l'adoption |
| H7 | Modération | Toute première annonce d'un compte passe en modération ; les suivantes d'un compte vérifié sont publiées directement mais restent signalables | Équilibre qualité / délai |
| H8 | Vérification agence | Badge « Agence vérifiée » attribué manuellement sur justificatifs (NINEA, RCCM) | Liste exacte des pièces à confirmer |
| H9 | Localisation | Référentiel régions → départements → communes / quartiers, avec Dakar et sa banlieue chargés en premier ; coordonnées GPS optionnelles | Recherche locale pertinente dès le lancement |
| H10 | Langue | Interface en français ; architecture i18n prête pour l'anglais et le wolof | Coût faible si prévu tôt |
| H11 | Notifications | Interfaces abstraites par canal ; e-mail et push actifs au MVP, WhatsApp et SMS branchés dès qu'un fournisseur est choisi | Fournisseurs non arrêtés |
| H12 | Hébergement | Un VPS Linux unique (Nginx, PHP-FPM, PostgreSQL + PostGIS, Redis, Supervisor) pour la recette, stockage des médias compatible S3 | Coût maîtrisé, migration simple |

## 3. Rôles et matrice des permissions

Les droits sont vérifiés côté serveur par des Policies Laravel ; l'interface ne fait que masquer ce qui est déjà interdit. Un même compte peut cumuler « Client » et « Propriétaire particulier » ; un agent n'agit que dans le périmètre de son agence.

| Action | Visiteur | Client | Particulier | Agent | Gérant d'agence | Admin | Super admin |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Consulter et rechercher les annonces publiées | Oui | Oui | Oui | Oui | Oui | Oui | Oui |
| Gérer ses favoris et recherches enregistrées | Non | Oui | Oui | Oui | Oui | — | — |
| Envoyer une demande de contact ou de visite | Non | Oui | Oui | Oui | Oui | — | — |
| Publier une annonce | Non | Non | Ses biens | Pour l'agence | Pour l'agence | Non | Non |
| Modifier / archiver une annonce | Non | Non | Les siennes | Celles qui lui sont attribuées | Toutes celles de l'agence | Modérer | Modérer |
| Traiter les demandes reçues | Non | Non | Les siennes | Celles qui lui sont attribuées | Toutes, avec réattribution | Lecture | Lecture |
| Inviter / suspendre un agent | Non | Non | Non | Non | Oui | Oui | Oui |
| Demander une formule ou une mise en avant | Non | Non | Oui | Non | Oui | — | — |
| Activer / suspendre une souscription | Non | Non | Non | Non | Non | Oui | Oui |
| Modérer annonces et signalements | Non | Non | Non | Non | Non | Oui | Oui |
| Vérifier une agence ou un propriétaire | Non | Non | Non | Non | Non | Oui | Oui |
| Gérer le catalogue de formules et les prix | Non | Non | Non | Non | Non | Non | Oui |
| Gérer les administrateurs et paramètres globaux | Non | Non | Non | Non | Non | Non | Oui |

Le rôle **Gérant d'agence** est ajouté par rapport à la v1.1 : il distingue le responsable de l'agence de ses agents, condition nécessaire pour suspendre un agent sans perdre l'historique.

## 4. Spécifications fonctionnelles par module

Neuf modules composent le MVP. Chaque exigence porte un identifiant réutilisé dans les tâches Claude Code et les tests.

### M1 — Comptes et authentification

- **AUTH-01** Inscription par numéro de téléphone (format +221) ou e-mail, avec mot de passe. Le téléphone est l'identifiant principal.
- **AUTH-02** Vérification du téléphone par code OTP à 6 chiffres, valable 10 minutes, 5 tentatives maximum. Tant que le fournisseur SMS n'est pas choisi, l'OTP est envoyé par e-mail ou journalisé en environnement de développement.
- **AUTH-03** Connexion web par session (Laravel Sanctum, cookie) ; connexion mobile par jeton Sanctum révocable par appareil.
- **AUTH-04** Réinitialisation du mot de passe par OTP ou lien e-mail.
- **AUTH-05** Choix du type de compte à l'inscription : Particulier ou Agence. Un compte Agence crée l'organisation et en devient le gérant.
- **AUTH-06** Profil : nom, photo, téléphone, WhatsApp (case « même numéro »), e-mail, ville, préférences de notification.
- **AUTH-07** Suppression de compte à la demande : anonymisation des données personnelles, conservation des journaux d'audit.
- **AUTH-08** Limitation de débit sur connexion, OTP et formulaires de contact.

### M2 — Annonces

- **ANN-01** Création en 4 étapes : type et transaction, localisation, caractéristiques, médias. Brouillon sauvegardé à chaque étape.
- **ANN-02** Types de bien : appartement, maison / villa, studio, chambre, terrain, bureau, local commercial, entrepôt, immeuble.
- **ANN-03** Transactions : vente, location longue durée, location meublée courte durée.
- **ANN-04** Champs communs : titre, description, prix en XOF, prix négociable, surface en m², adresse, quartier, commune, coordonnées GPS optionnelles.
- **ANN-05** Champs selon le type : pièces, chambres, salles de bain, étage, meublé, parking, gardiennage, climatisation, eau / électricité ; pour un terrain, statut foncier (titre foncier, bail, délibération) et viabilisation.
- **ANN-06** Location : loyer mensuel, caution en nombre de mois, avance, frais d'agence.
- **ANN-07** Médias : 1 à 20 photos JPEG / PNG / WebP de 10 Mo maximum chacune, compressées et redimensionnées côté serveur (miniature, moyenne, grande), ordre modifiable, photo de couverture obligatoire.
- **ANN-08** Référence unique lisible (ex. CV-2026-000123) et lien partageable sur WhatsApp avec aperçu Open Graph.
- **ANN-09** Actions annonceur : modifier, mettre en pause, marquer comme vendu / loué, archiver, dupliquer.
- **ANN-10** Signalement d'une annonce par tout utilisateur connecté (motif + commentaire).
- **ANN-11** Expiration automatique après une durée configurable (hypothèse : 60 jours) avec rappel 7 jours avant.

### M3 — Recherche et consultation

- **RCH-01** Recherche par transaction, type, localisation (région, commune, quartier), prix min / max, surface, chambres, meublé.
- **RCH-02** Tri : pertinence, plus récentes, prix croissant / décroissant. La pertinence place d'abord les annonces « Remontée » actives, puis la fraîcheur.
- **RCH-03** Affichage liste et carte (PostGIS, recherche dans la zone visible) ; la carte affiche une position approximative si l'annonceur l'a choisie.
- **RCH-04** Fiche détaillée : galerie, caractéristiques, carte, annonceur avec badge vérifié, boutons Appeler, WhatsApp, Demander une visite, Envoyer un message.
- **RCH-05** Annonces similaires (même type, même commune, prix ±25 %).
- **RCH-06** Pages SEO par combinaison ville × type × transaction (ex. « Appartements à louer à Dakar Plateau »), rendues côté serveur par Next.js.
- **RCH-07** Compteurs de vues et de contacts par annonce, dédoublonnés par visiteur et par jour.

### M4 — Espace agence

- **AGC-01** Profil public d'agence : logo, description, adresse, contacts, zones couvertes, liste de ses annonces.
- **AGC-02** Invitation d'un agent par téléphone ou e-mail ; l'agent accepte et rejoint l'agence.
- **AGC-03** Rôles internes : gérant, agent. Un agent ne voit que les annonces et demandes qui lui sont attribuées, sauf droit étendu accordé par le gérant.
- **AGC-04** Attribution et réattribution d'une annonce ou d'une demande à un agent.
- **AGC-05** Suspension d'un agent : accès coupé immédiatement, ses annonces et demandes restent à l'agence et sont réattribuées au gérant.
- **AGC-06** Tableau de bord : annonces actives, vues, demandes reçues, taux de réponse, visites planifiées, par agent et global.
- **AGC-07** Demande de vérification avec dépôt de justificatifs (stockage privé, accès admin uniquement).

### M5 — Demandes, prospects et visites

- **DEM-01** Une demande de contact contient le message, le téléphone du demandeur et le canal préféré ; elle crée un fil de messages entre le demandeur et l'annonceur.
- **DEM-02** Statuts d'une demande : nouvelle, en cours, visite planifiée, conclue, perdue, fermée.
- **DEM-03** Demande de visite avec 1 à 3 créneaux proposés ; l'annonceur confirme un créneau, en propose un autre ou refuse.
- **DEM-04** Rappel de visite 24 h et 2 h avant, aux deux parties.
- **DEM-05** Notes internes privées sur un prospect (visibles de l'agence uniquement).
- **DEM-06** Les coordonnées du demandeur ne sont visibles que de l'annonceur et, pour une agence, des membres autorisés.

### M6 — Favoris et alertes

- **FAV-01** Ajouter / retirer une annonce des favoris ; notification si un favori baisse de prix ou n'est plus disponible.
- **FAV-02** Enregistrer une recherche et recevoir une alerte (quotidienne ou immédiate) pour les nouvelles annonces correspondantes.

### M7 — Offre commerciale sans paiement en ligne

- **COM-01** Catalogue de formules (plans) : nom, cible (particulier / agence), prix XOF, période (mensuelle, trimestrielle, annuelle), quotas (annonces actives, agents, photos par annonce, crédits de mise en avant inclus), fonctionnalités (statistiques avancées, badge, page agence enrichie).
- **COM-02** Catalogue d'options premium : « À la une », « Remontée », durée, prix.
- **COM-03** L'annonceur choisit une formule ou une option et envoie une demande ; il voit les instructions de règlement hors ligne et une référence de paiement.
- **COM-04** L'administrateur enregistre l'encaissement (moyen, référence, montant, date, pièce jointe) puis active la souscription. Statut de règlement : non réglé, déclaré, confirmé. Jamais « payé en ligne ».
- **COM-05** Les droits (quotas, fonctionnalités) sont calculés à partir des souscriptions actives, vérifiés à chaque action concernée, et retombent automatiquement au niveau gratuit à l'expiration.
- **COM-06** Rappels d'expiration à 7 jours et 1 jour ; à l'expiration, les annonces au-delà du quota gratuit passent en pause (jamais supprimées), au choix de l'annonceur ou les plus anciennes par défaut.
- **COM-07** Historique complet : demandes, activations, changements de formule, suspensions, avec auteur et date.

### M8 — Notifications

- **NOT-01** Événements notifiés : OTP, annonce approuvée / refusée, nouvelle demande, réponse, visite confirmée / rappel, alerte de recherche, baisse de prix, expiration d'annonce ou de souscription, activation de souscription, invitation d'agent.
- **NOT-02** Canaux : in-app, push (FCM), e-mail, WhatsApp, SMS. Chaque canal est une implémentation interchangeable.
- **NOT-03** Préférences par utilisateur et par type d'événement ; l'OTP et les messages de sécurité ne sont pas désactivables.
- **NOT-04** Envoi asynchrone par file d'attente, 3 nouvelles tentatives, journal de chaque envoi (canal, statut, erreur fournisseur).
- **NOT-05** Modèles de message versionnés en français.

### M9 — Back-office d'administration

- **ADM-01** File de modération : annonces en attente, motif de refus choisi dans une liste + commentaire, approbation en un clic.
- **ADM-02** Traitement des signalements : masquer l'annonce, avertir ou suspendre l'annonceur.
- **ADM-03** Gestion des utilisateurs et des agences : recherche, suspension, vérification, historique.
- **ADM-04** Gestion commerciale : demandes de souscription, enregistrement des encaissements, activations, souscriptions expirant bientôt.
- **ADM-05** Référentiels : localisations, types de biens, équipements, motifs de refus, modèles de notification.
- **ADM-06** Tableau de bord : inscriptions, annonces publiées, demandes envoyées, souscriptions actives, revenu déclaré du mois.
- **ADM-07** Journal d'audit consultable et filtrable, non modifiable.
- **ADM-08** Le back-office est intégré à Next.js (espace /admin) sur la même API, avec double authentification pour les administrateurs (hypothèse).

## 5. Cycles de vie et règles métier

Quatre objets ont un cycle de vie contrôlé ; toute transition passe par un service dédié qui vérifie qu'elle est autorisée et l'inscrit au journal d'audit. Aucune transition n'est faite par simple mise à jour d'un champ.

### Annonce

| De | Vers | Déclencheur | Règle |
| --- | --- | --- | --- |
| Brouillon | En modération | Annonceur soumet | Champs obligatoires et photo de couverture présents ; quota d'annonces actives respecté |
| Brouillon | Publiée | Annonceur soumet | Compte vérifié et annonce non première (H7) |
| En modération | Publiée | Admin approuve | Date de publication et date d'expiration posées |
| En modération | Refusée | Admin refuse | Motif obligatoire, notifié à l'annonceur |
| Refusée | En modération | Annonceur corrige | — |
| Publiée | En pause | Annonceur, ou fin de souscription au-delà du quota | Ne compte plus dans le quota actif |
| En pause | Publiée | Annonceur | Quota respecté au moment de la réactivation |
| Publiée | Vendue / Louée | Annonceur | Reste consultable 30 jours avec bandeau, puis archivée |
| Publiée | Expirée | Tâche planifiée | Republiable par l'annonceur |
| Publiée | Masquée | Admin après signalement | Invisible du public, visible de l'annonceur avec le motif |
| Tout état | Archivée | Annonceur ou admin | Jamais de suppression physique au MVP |

Modifier le prix, le type ou la localisation d'une annonce publiée d'un compte non vérifié la renvoie en modération.

### Souscription

| De | Vers | Déclencheur | Règle |
| --- | --- | --- | --- |
| — | En attente | Annonceur demande une formule | Référence de paiement générée ; aucun avantage accordé |
| En attente | Active | Admin active | Encaissement enregistré au statut « confirmé » ; dates de début et de fin posées |
| En attente | Annulée | Annonceur ou admin | Motif enregistré |
| Active | Suspendue | Admin | Avantages retirés immédiatement ; motif obligatoire |
| Suspendue | Active | Admin | La date de fin n'est pas prolongée automatiquement |
| Active | Expirée | Tâche planifiée à la date de fin | Retour aux droits gratuits (COM-05, COM-06) |
| Active | Remplacée | Changement de formule activé | L'ancienne se termine à la date d'activation de la nouvelle |

Un annonceur a au plus une souscription active par organisation (agence) ou par compte (particulier). Une souscription ne peut jamais être activée sans un encaissement confirmé rattaché.

### Mise en avant

Une option premium s'applique à une seule annonce publiée, pour une durée fixe. Elle passe par les mêmes états que la souscription (en attente, active, expirée, annulée). Si l'annonce quitte l'état Publiée, la mise en avant est suspendue ; le temps restant n'est pas rendu au MVP (règle à arbitrer). Le nombre d'annonces « À la une » simultanées est plafonné (paramètre) et elles tournent de façon équitable.

### Demande et visite

Une demande va de Nouvelle à En cours dès la première réponse, puis Visite planifiée, Conclue ou Perdue. Une visite est Proposée, Confirmée, Reportée, Annulée ou Effectuée. Une demande sans réponse depuis 48 h déclenche un rappel à l'annonceur ; ce délai alimente le taux de réponse affiché aux agences.

## 6. Modèle de données

Le modèle compte une trentaine de tables réparties en 6 domaines. Clés primaires en `bigint`, identifiants publics en ULID pour les URL, horodatages et `deleted_at` (suppression logique) sur les tables métier. Les états sont des `enum` PHP adossés à des colonnes `varchar` contraintes par `CHECK`.

| Domaine | Table | Colonnes clés | Contraintes et index |
| --- | --- | --- | --- |
| Identité | users | ulid, name, phone (unique), email (unique, null), whatsapp, password, phone\_verified\_at, account\_type (individual / agency), status, locale | index status |
| Identité | otp\_codes | phone, code\_hash, purpose, expires\_at, attempts | index (phone, purpose) |
| Identité | roles, permissions, model\_has\_roles | via spatie/laravel-permission | rôles plateforme uniquement |
| Organisation | agencies | ulid, name, slug (unique), logo\_path, ninea, rccm, address, locality\_id, verified\_at, status | index slug, verified\_at |
| Organisation | agency\_members | agency\_id, user\_id, role (manager / agent), can\_see\_all, status, invited\_at, joined\_at, suspended\_at | unique (agency\_id, user\_id) |
| Organisation | agency\_invitations | agency\_id, phone / email, token\_hash, expires\_at, accepted\_at | — |
| Organisation | verification\_requests | subject\_type, subject\_id, status, reviewed\_by, reviewed\_at, note | documents en stockage privé |
| Référentiel | localities | parent\_id, level (region / department / commune / neighborhood), name, slug, geom (PostGIS) | index GIST sur geom, index (parent\_id, level) |
| Référentiel | property\_types, amenities | code, label, applicable\_to | — |
| Annonces | properties | ulid, reference (unique), owner\_user\_id, agency\_id (null), assigned\_to, type, transaction, title, description, price, negotiable, surface\_m2, rooms, bedrooms, bathrooms, furnished, attributes (jsonb), locality\_id, address, location (geography Point), location\_precision, status, published\_at, expires\_at, views\_count, contacts\_count | index (status, transaction, type, locality\_id, price), GIST sur location, GIN sur attributes |
| Annonces | property\_images | property\_id, path, sizes (jsonb), position, is\_cover | une seule couverture par annonce |
| Annonces | property\_amenity | property\_id, amenity\_id | clé composite |
| Annonces | property\_reports | property\_id, reporter\_id, reason, comment, status, handled\_by | — |
| Annonces | property\_status\_history | property\_id, from, to, actor\_id, reason | — |
| Relation | favorites | user\_id, property\_id | unique (user\_id, property\_id) |
| Relation | saved\_searches | user\_id, criteria (jsonb), frequency, last\_notified\_at | — |
| Relation | inquiries | ulid, property\_id, requester\_id, owner\_user\_id, agency\_id, assigned\_to, status, preferred\_channel, last\_activity\_at | index (agency\_id, status), (owner\_user\_id, status) |
| Relation | inquiry\_messages | inquiry\_id, sender\_id, body, read\_at | — |
| Relation | inquiry\_notes | inquiry\_id, author\_id, body | visibles de l'agence uniquement |
| Relation | visits | inquiry\_id, proposed\_slots (jsonb), scheduled\_at, status, reminder\_sent\_at | — |
| Commercial | plans | code, name, audience, price\_xof, period, limits (jsonb), features (jsonb), is\_active, sort | prix modifiable sans toucher aux souscriptions existantes |
| Commercial | premium\_products | code, kind (featured / boost), duration\_days, price\_xof, is\_active | — |
| Commercial | subscriptions | subscriber\_type, subscriber\_id, plan\_id, plan\_snapshot (jsonb), status, starts\_at, ends\_at, payment\_reference, activated\_by, replaced\_by\_id | unique partiel : une seule active par souscripteur |
| Commercial | premium\_listings | property\_id, premium\_product\_id, status, starts\_at, ends\_at, payment\_reference, activated\_by | index (status, ends\_at) |
| Commercial | offline\_payments | payable\_type, payable\_id, method (wave / orange\_money / transfer / cash / other), amount\_xof, external\_reference, received\_at, proof\_path, status (declared / confirmed / rejected), recorded\_by | jamais créé par l'annonceur au statut confirmé |
| Système | notifications, notification\_deliveries | notifiable, type, data / channel, provider, status, attempts, error, sent\_at | index (status, channel) |
| Système | audit\_logs | actor\_id, action, subject\_type, subject\_id, before (jsonb), after (jsonb), ip, user\_agent | en ajout seul, pas de mise à jour ni de suppression |

`plan_snapshot` fige les quotas et le prix au moment de l'activation : une modification ultérieure du catalogue ne change pas une souscription en cours. Les droits effectifs sont calculés par un service `EntitlementService` et mis en cache par souscripteur.

## 7. Contrat d'API REST v1

Une seule API JSON, préfixée `/api/v1`, sert le web et le mobile. Réponses en JSON:API simplifié (`data`, `meta`, `links`), erreurs normalisées (`code`, `message`, `errors` par champ), pagination par curseur, documentation OpenAPI générée automatiquement (Scribe).

| Domaine | Méthode et route | Accès | Exigence |
| --- | --- | --- | --- |
| Auth | POST /auth/register · /auth/otp/send · /auth/otp/verify · /auth/login · /auth/logout | Public / connecté | AUTH-01 à 04 |
| Auth | GET, PATCH /me · DELETE /me | Connecté | AUTH-06, 07 |
| Référentiels | GET /localities?parent= · /property-types · /amenities | Public | ANN-02, RCH-01 |
| Recherche | GET /properties?filters… · /properties/map?bbox= · /properties/{ulid} · /properties/{ulid}/similar | Public | RCH-01 à 05 |
| Annonces | GET, POST /my/properties · PATCH, DELETE /my/properties/{ulid} · POST /my/properties/{ulid}/submit · /pause · /resume · /mark-sold · /duplicate | Annonceur | ANN-01 à 09 |
| Médias | POST /my/properties/{ulid}/images · PATCH /images/order · DELETE /images/{id} | Annonceur | ANN-07 |
| Signalement | POST /properties/{ulid}/reports | Connecté | ANN-10 |
| Favoris | GET, POST, DELETE /my/favorites · /my/saved-searches | Connecté | FAV-01, 02 |
| Demandes | POST /properties/{ulid}/inquiries · GET /my/inquiries · GET, POST /inquiries/{ulid}/messages · PATCH /inquiries/{ulid} (statut, attribution) · POST /inquiries/{ulid}/notes | Demandeur / annonceur | DEM-01 à 06 |
| Visites | POST /inquiries/{ulid}/visits · PATCH /visits/{id} (confirmer, reporter, annuler, effectuée) | Parties | DEM-03, 04 |
| Agence | GET, PATCH /agency · GET /agency/members · POST /agency/invitations · PATCH /agency/members/{id} · POST /agency/verification · GET /agency/stats | Gérant / agent | AGC-01 à 07 |
| Public agence | GET /agencies/{slug} | Public | AGC-01 |
| Commercial | GET /plans · /premium-products · POST /my/subscription-requests · GET /my/subscription · POST /my/properties/{ulid}/premium · GET /my/entitlements | Annonceur | COM-01 à 06 |
| Notifications | GET /my/notifications · PATCH /my/notifications/read · GET, PATCH /my/notification-preferences · POST /my/devices | Connecté | NOT-01 à 03 |
| Admin | /admin/moderation · /admin/reports · /admin/users · /admin/agencies · /admin/subscriptions (activate, suspend) · /admin/offline-payments · /admin/plans · /admin/referentials · /admin/stats · /admin/audit-logs | Admin / super admin | ADM-01 à 08 |

Toutes les routes `/my`, `/agency` et `/admin` passent par une Policy ; un test automatisé vérifie pour chacune qu'un utilisateur d'une autre agence reçoit 403 ou 404.

## 8. Exigences non fonctionnelles

Les cibles ci-dessous tiennent compte d'un usage majoritairement mobile sur réseau 3G / 4G au Sénégal ; ce sont des hypothèses de travail mesurées en recette.

| Domaine | Exigence | Cible MVP |
| --- | --- | --- |
| Performance | Temps de réponse API (p95) hors upload | < 400 ms |
| Performance | Page de résultats web, mobile 4G simulée | LCP < 2,5 s ; poids initial < 500 Ko hors images |
| Performance | Images | WebP, chargement différé, 3 tailles générées |
| Sécurité | Mots de passe et OTP | bcrypt / argon2 ; OTP stockés hachés |
| Sécurité | Autorisation | Policies serveur sur 100 % des routes protégées, testées |
| Sécurité | En-têtes et transport | HTTPS obligatoire, HSTS, CSP, CORS limité aux domaines ChezVous |
| Sécurité | Fichiers | Validation du type réel, renommage, justificatifs en stockage privé avec URL signées temporaires |
| Données personnelles | Conformité | Loi sénégalaise n° 2008-12 et CDP : consentement, finalité, droit d'accès et de suppression ; déclaration CDP à vérifier avant lancement |
| Disponibilité | Service | 99 % mensuel en phase pilote ; sauvegarde PostgreSQL quotidienne, rétention 14 jours, restauration testée |
| Observabilité | Journaux et erreurs | Logs structurés, suivi des erreurs (Sentry ou équivalent), Horizon pour les files |
| Qualité | Tests | Pest (backend) ≥ 80 % sur services métier et Policies ; tests E2E Playwright sur les 5 parcours clés |
| Qualité | Code | Laravel Pint, PHPStan niveau 6, ESLint + TypeScript strict, `flutter analyze` sans erreur |
| Accessibilité | Web | Contraste AA, navigation clavier, libellés de formulaires |
| Compatibilité | Navigateurs et OS | 2 dernières versions de Chrome, Safari, Firefox ; Android 8+ ; iOS 15+ |
| Exploitation | Livraison | CI GitHub Actions (tests, analyse, build) ; déploiement sans interruption ; variables sensibles hors dépôt |

Les 5 parcours clés testés de bout en bout : rechercher et contacter, publier une annonce, traiter une demande en agence, modérer, activer une souscription.

## 9. Plan d'exécution pour Claude Code

Le MVP se construit en 9 phases séquentielles : 0 à 7 pour l'API et le web, 8 pour l'application Flutter. Une phase n'est commencée que lorsque la précédente passe ses critères de recette (section 10).

| Phase | Contenu | Exigences | Livrable vérifiable | Arbitrage bloquant |
| --- | --- | --- | --- | --- |
| 0. Socle | Dépôt Git, monorepo `backend/` `web/` `mobile/`, Laravel, Next.js, PostgreSQL + PostGIS, Redis, CI, `CLAUDE.md` | — | `git clone` + une commande de démarrage = API et web en local, CI verte | Aucun |
| 1. Comptes | Inscription, OTP, connexion web et mobile, rôles, profil, audit | AUTH-01 à 08 | Parcours d'inscription complet testé | Aucun |
| 2. Annonces | Référentiels, création en 4 étapes, médias, cycle de vie, modération minimale | ANN-01 à 11, ADM-01 | Un particulier publie une annonce validée par un admin | Aucun (H6, H7 appliquées) |
| 3. Recherche et web public | Filtres, carte PostGIS, fiche, similaires, pages SEO, compteurs | RCH-01 à 07 | Site public consultable et indexable | Aucun |
| 4. Agences | Organisation, invitations, rôles internes, attribution, suspension, tableau de bord, vérification | AGC-01 à 07 | Une agence à 3 agents fonctionne avec cloisonnement testé | Pièces de vérification (H8) |
| 5. Demandes et favoris | Demandes, messagerie, visites, notes, favoris, alertes | DEM-01 à 06, FAV-01, 02 | Parcours rechercher → contacter → visite confirmée | Aucun |
| 6. Offre commerciale | Catalogue, demandes, encaissements hors ligne, activation, droits, expiration, mises en avant | COM-01 à 07 | Activation manuelle tracée, quotas appliqués, expiration correcte | Prix et quotas (valeurs à 0 en attendant) |
| 7. Notifications, admin, durcissement | Canaux, préférences, journal d'envois, back-office complet, sécurité, performance, E2E | NOT-01 à 05, ADM-02 à 08, section 8 | MVP web prêt pour recette pilote | Fournisseurs WhatsApp / SMS |
| 8. Application Flutter | Android et iOS sur la même API : recherche, fiche, favoris, demandes, publication, espace agence simplifié, push | Parcours clés | Builds AAB et iOS installables en test interne | Calendrier mobile |

### Règles de travail pour Claude Code

- Commencer chaque phase par un audit de l'existant et un plan court, validé avant d'écrire du code.
- Une branche Git par phase, des commits atomiques en français au format Conventional Commits, aucun commit sur `main` sans tests verts.
- Aucun prix, quota ou durée en dur : configuration ou table `plans` / `settings`.
- Toute transition d'état passe par un service et écrit dans `audit_logs`.
- Chaque route protégée a un test d'autorisation négatif (autre utilisateur, autre agence).
- Fin de phase : résumé des fichiers modifiés, commandes de test exécutées et résultats, écarts avec la spécification.

### Fichier CLAUDE.md à placer à la racine (phase 0)

```markdown
# ChezVous — instructions projet

Plateforme immobilière (Sénégal). Monorepo :
- backend/ : Laravel (dernière version stable, à figer en phase 0), PHP 8.3+, PostgreSQL 16+ avec PostGIS, Redis, Sanctum, spatie/laravel-permission, Pest
- web/ : Next.js (App Router), TypeScript strict, Tailwind, rendu serveur pour les pages publiques
- mobile/ : Flutter (Android + iOS), consomme la même API

Référence fonctionnelle : docs/specs-mvp.md (identifiants AUTH-, ANN-, RCH-, AGC-, DEM-, FAV-, COM-, NOT-, ADM-).

Règles :
1. Autorisation côté serveur via Policies ; jamais seulement dans l'interface.
2. Aucun prix, quota ou durée en dur.
3. Transitions d'état via services dédiés + audit_logs.
4. Aucun paiement en ligne au MVP ; ne jamais marquer une souscription comme payée sans encaissement confirmé par un admin.
5. Devise XOF, montants entiers. Interface en français, i18n prête.
6. Tests Pest pour chaque service métier et chaque Policy ; ./vendor/bin/pint et phpstan avant commit.
7. Commits en français, Conventional Commits, une branche par phase.
8. Avant de coder : auditer l'existant, proposer un plan, attendre validation.
```

### Modèle de prompt par phase

Chaque phase se lance avec le même gabarit ; seul le bloc « Périmètre » change.

```text
Phase <N> — <titre> du MVP ChezVous.

Contexte : lis CLAUDE.md et docs/specs-mvp.md (sections 3 à 8).
Périmètre : <liste des exigences, ex. ANN-01 à ANN-11 et ADM-01>.

Étapes :
1. Audite l'existant (git log, structure, migrations, routes, tests) et résume l'état.
2. Propose un plan : migrations, modèles, enums, services, Policies, contrôleurs, routes, écrans web, tests. ATTENDS ma validation.
3. Implémente sur la branche phase-<N>-<slug>, par commits atomiques.
4. Écris les tests Pest (règles métier, transitions, autorisations négatives) et, côté web, les écrans concernés.
5. Exécute : php artisan test, pint --test, phpstan, npm run lint, npm run build.
6. Rends : fichiers modifiés, résultats des commandes, critères de recette de la phase (section 10) cochés ou non, écarts éventuels.

Contraintes : aucune valeur commerciale en dur, audit_logs sur chaque transition, pas de paiement en ligne.
```

### Blocs « Périmètre » par phase

1. **Phase 0** — Initialise le dépôt Git et le monorepo ; installe Laravel, Sanctum, spatie/laravel-permission, Pest, Pint, PHPStan ; active PostGIS ; crée Next.js TypeScript + Tailwind ; ajoute docker-compose pour PostgreSQL / PostGIS et Redis en développement ; GitHub Actions (tests backend, lint et build web) ; copie la spécification dans `docs/specs-mvp.md` ; crée `CLAUDE.md` ; seeders des rôles plateforme et d'un super admin.
2. **Phase 1** — AUTH-01 à AUTH-08 ; interface `OtpSender` avec implémentations `LogOtpSender` et `MailOtpSender` ; table `audit_logs` et trait `Auditable` ; écrans web inscription, OTP, connexion, profil.
3. **Phase 2** — Référentiel `localities` (seed Dakar et banlieue, puis régions), `property_types`, `amenities` ; ANN-01 à ANN-11 ; `PropertyStateService` ; traitement des images en file d'attente ; file de modération ADM-01 ; quota particulier configurable (H6).
4. **Phase 3** — RCH-01 à RCH-07 ; recherche par zone visible (bbox PostGIS) ; pages publiques Next.js en rendu serveur, sitemap.xml, balises Open Graph ; compteurs dédoublonnés via Redis.
5. **Phase 4** — AGC-01 à AGC-07 ; Policies cloisonnées par agence avec tests négatifs systématiques ; suspension d'agent avec réattribution au gérant ; stockage privé des justificatifs.
6. **Phase 5** — DEM-01 à DEM-06, FAV-01, FAV-02 ; rappels planifiés (48 h sans réponse, visites 24 h et 2 h) ; masquage des coordonnées selon DEM-06.
7. **Phase 6** — COM-01 à COM-07 ; `SubscriptionService`, `EntitlementService` avec `plan_snapshot` ; `offline_payments` ; tâche quotidienne d'expiration ; mises en avant avec rotation et plafond ; formules seedées avec prix et quotas à 0.
8. **Phase 7** — NOT-01 à NOT-05 avec interface par canal (`PushChannel` FCM, `MailChannel`, `WhatsAppChannel` et `SmsChannel` en bouchon) ; ADM-02 à ADM-08 ; exigences de la section 8 ; tests E2E Playwright des 5 parcours clés.
9. **Phase 8** — Projet Flutter dans `mobile/` : architecture par fonctionnalités, client API généré depuis l'OpenAPI, authentification par jeton, recherche, fiche, favoris, demandes et messages, publication d'annonce avec compression des photos avant envoi, push FCM ; builds AAB et iOS (Codemagic) en test interne.

## 10. Critères de recette par phase

Une phase est validée quand tous ses critères sont démontrés par un test automatisé ou une démonstration enregistrée, et que la CI est verte.

**Phase 0 — Socle**

- [ ] Le dépôt démarre en local avec une seule commande documentée dans le README
- [ ] PostGIS est actif (`SELECT postgis_version()` répond)
- [ ] La CI exécute tests, Pint, PHPStan, lint et build web, et passe

**Phase 1 — Comptes**

- [ ] Inscription par téléphone, OTP expiré ou épuisé refusé
- [ ] Un jeton mobile révoqué ne donne plus accès
- [ ] 6 tentatives de connexion rapides déclenchent la limitation
- [ ] La suppression de compte anonymise sans effacer l'audit

**Phase 2 — Annonces**

- [ ] Un particulier publie une annonce ; la première passe en modération
- [ ] Le quota gratuit bloque une annonce active de trop, avec message clair
- [ ] Les transitions interdites (ex. Refusée → Publiée) sont rejetées et testées
- [ ] Les photos sont redimensionnées en 3 tailles et la couverture est unique

**Phase 3 — Recherche**

- [ ] Les filtres combinés renvoient uniquement des annonces publiées et non expirées
- [ ] La recherche sur carte ne renvoie que la zone visible
- [ ] Une page SEO est rendue côté serveur avec titre, description et Open Graph
- [ ] p95 de l'API de recherche < 400 ms sur un jeu de 10 000 annonces

**Phase 4 — Agences**

- [ ] Un agent ne voit ni les annonces ni les demandes d'une autre agence (403 / 404 testés sur toutes les routes)
- [ ] Un agent sans droit étendu ne voit que ce qui lui est attribué
- [ ] Suspendre un agent coupe son accès et réattribue ses éléments au gérant, historique intact

**Phase 5 — Demandes et favoris**

- [ ] Les coordonnées du demandeur sont invisibles des tiers
- [ ] Une visite confirmée déclenche les rappels à 24 h et 2 h
- [ ] Une recherche enregistrée produit une alerte pour une nouvelle annonce correspondante

**Phase 6 — Offre commerciale**

- [ ] Une souscription ne peut pas être activée sans encaissement confirmé
- [ ] Aucun écran n'affiche « payé » pour un encaissement non confirmé
- [ ] Les quotas d'une formule active sont appliqués ; à l'expiration, les droits retombent au gratuit et les annonces excédentaires passent en pause
- [ ] Modifier le prix d'une formule ne change pas une souscription en cours
- [ ] Chaque activation, suspension et changement de formule apparaît dans l'audit

**Phase 7 — Notifications et back-office**

- [ ] Chaque envoi est journalisé avec son statut ; un échec fournisseur est visible et relancé 3 fois
- [ ] L'OTP n'est pas désactivable dans les préférences
- [ ] Les 5 parcours clés passent en E2E
- [ ] Sauvegarde restaurée avec succès sur un environnement vierge

**Phase 8 — Mobile**

- [ ] Les parcours rechercher → contacter et publier une annonce fonctionnent sur Android et iOS
- [ ] Une photo de 8 Mo est compressée avant envoi et acceptée
- [ ] Une notification push arrive application fermée sur les deux plateformes
- [ ] Builds installables via test interne Play Console et TestFlight

## 11. Points à arbitrer

Aucun de ces points ne bloque le démarrage : grâce aux valeurs provisoires, les phases 0 à 5 peuvent avancer sans attendre. Chacun doit être tranché avant la phase indiquée.

| Point | Question | Avant la phase | Valeur provisoire |
| --- | --- | --- | --- |
| Vérification des agences | Quelles pièces exiger (NINEA, RCCM, carte professionnelle, autre) ? | 4 | NINEA + RCCM |
| Vérification des propriétaires | Faut-il vérifier les particuliers (pièce d'identité, titre de propriété) ou seulement le téléphone ? | 4 | Téléphone uniquement |
| Tarifs des formules | Prix mensuels de Pro Starter, Business, Premium ? Remise trimestrielle / annuelle ? | 6 | 0 XOF |
| Quotas des formules | Annonces actives, agents et crédits de mise en avant par formule ? | 6 | 0 |
| Prix des mises en avant | Prix « À la une » et « Remontée » pour 7, 15 et 30 jours ? | 6 | 0 XOF |
| Procédure commerciale | Activation manuelle confirmée ? Quels moyens de règlement hors ligne afficher ? | 6 | Manuelle ; Wave, Orange Money, virement, espèces |
| Mise en avant interrompue | Le temps restant est-il rendu si l'annonce passe en pause ou est vendue ? | 6 | Non rendu |
| Fournisseurs WhatsApp / SMS | Quel fournisseur (API WhatsApp Business officielle, agrégateur SMS local) et quel budget ? | 7 | Canaux en bouchon |
| Conditions juridiques | CGU, politique de confidentialité, politique de modération, déclaration CDP | 7 | Gabarits à rédiger |
| Hébergement | Fournisseur du VPS, région, budget mensuel | 7 | VPS Linux unique (H12) |
| Calendrier mobile | Applications dans la première livraison du MVP ou après la recette web ? | 8 | Après la recette web (H1) |
| Comptes développeur | Comptes Google Play et Apple Developer au nom de quelle entité ? | 8 | — |

Dès que ces réponses sont connues, les valeurs provisoires sont remplacées dans la configuration et ce tableau passe en section 2 comme décisions.
