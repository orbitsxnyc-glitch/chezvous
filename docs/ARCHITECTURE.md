# Architecture cible

Statut : cible documentée, rien n'est encore implémenté. Source produit : la présentation dans `docs/`. Le cahier des charges détaillé n'a pas été fourni au dépôt.

## Principes

- Monolithe modulaire avec une seule API REST consommée par le web et le mobile.
- Autorisations toujours vérifiées côté serveur.
- Un bien (`Property`) existe une seule fois, même avec plusieurs annonces.
- La confiance repose sur des contrôles explicites, datés et consultables, jamais sur une note opaque. Le paiement n'achète jamais un badge de confiance.
- « Document contrôlé » ne signifie pas « propriété juridiquement garantie ».

## Composants

Le modèle de données détaillé suit `docs/specs-mvp.md` section 6, corrigé par `docs/specs-addendum-passeport.md` (séparation `properties` / `listings`, ajout de `mandates`, `verification_checks`, `availability_confirmations`).

### API (`backend/`)
Laravel, API REST versionnée (`/api/v1`), modules par domaine, validation des entrées (Form Requests), authentification sécurisée, Policies, tests automatisés. Files d'attente pour les tâches de fraîcheur et les notifications.

### Web (`web/`)
Next.js pour le site public (SEO), l'espace professionnel et le back-office, avec une séparation logique des interfaces et des permissions.

### Mobile (`mobile/`)
Flutter pour Android et iOS, consommant la même API.

### Données
PostgreSQL avec PostGIS. Recherche initiale par PostgreSQL (filtres, plein texte, géographie), sans moteur externe.

Entités à modéliser (schéma non créé en Phase 0) :
`User`, `AdvertiserProfile`, `Property`, `Listing`, `Mandate`, `VerificationCheck`, `AvailabilityConfirmation`, `Media`, `Visit`, `Notification`, `Report`.

Relations de principe : un `Property` a plusieurs `Listing` ; un `Listing` est publié par un `AdvertiserProfile`, éventuellement sous un `Mandate` ; les `VerificationCheck` et `AvailabilityConfirmation` sont horodatés et conservés en historique.

### Médias
Deux espaces séparés : médias publics traités (photos), et documents privés (identité, mandats, justificatifs) accessibles seulement par URL signée à durée courte. Contrôle du type, de la taille et suppression des métadonnées EXIF au téléversement.

### Notifications
Interface commune à plusieurs canaux (WhatsApp, SMS, push). Aucun prestataire n'est branché tant que fournisseurs, coûts, identifiants et règles de consentement ne sont pas validés.
