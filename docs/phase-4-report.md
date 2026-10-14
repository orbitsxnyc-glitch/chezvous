# Phase 4 : rapport (agences)

Date : 2026-10-17. Branche `phase-4-agences`, locale.

## Livré

- **Modèle :** `agencies`, `agency_localities`, `agency_members` (un compte = une agence), `agency_invitations`, `verification_requests`, `verification_documents` ; clés étrangères sur `listings` et `mandates` ; contrôles du Passeport possibles sur une agence.
- **Inscription (AUTH-05) :** un compte Agence crée l'organisation et en devient le gérant (`agency_name` obligatoire).
- **Profil (AGC-01) :** modification par le gérant (contacts, NINEA, RCCM, zones, logo WebP) ; profil public `/api/v1/agencies/{slug}` sans NINEA, RCCM, justificatifs ni membres ; filtre `?agency=` sur la recherche.
- **Équipe (AGC-02, 05) :** invitation par téléphone ou e-mail (jeton haché, 7 jours, usage unique), acceptation, droit étendu, suspension avec accès coupé immédiatement et annonces réattribuées au gérant, réintégration.
- **Droits (AGC-03, 04) :** `ListingPolicy` : gérant = toute l'agence ; agent = ses annonces (modification) ; droit étendu = lecture de toute l'agence ; autrui = 404. Attribution et réattribution par le gérant.
- **Quota et confiance :** quota commun à l'agence (50) ; agence vérifiée publiée sans modération.
- **Tableau de bord (AGC-06) :** totaux et détail par agent (gérant), vue personnelle (agent), quota. Demandes, visites et taux de réponse : `null` (Phase 5).
- **Vérification (AGC-07) :** dépôt NINEA + RCCM en stockage privé, examen par un administrateur avec liens signés temporaires, approbation (badge, contrôle « professionnel » dans le Passeport) ou refus motivé.
- **Web :** champ « nom de l'agence » à l'inscription, page publique `/agences/{slug}`, acceptation d'invitation `/invitation/{jeton}`, espace `/agence` (statistiques, équipe, invitations).

## Vérifié par exécution

- `make check` : code 0 (Pest 253/253, PHPStan 0 erreur, Pint, Vitest 30/30, lint, typecheck, build, Flutter).
- Cloisonnement testé route par route : 404 pour l'annonce d'une autre agence (14 routes, gérant, agent et inconnu) ; agent sans droit étendu limité à ses annonces.
- Test de bout en bout avec la vraie API : page publique d'agence servie (200), badge affiché, aucune fuite de NINEA ni de RCCM.
- Un faux justificatif (code PHP nommé .pdf) est refusé sur son contenu réel.

## Limites connues

- Aucun envoi réel d'invitation par SMS (journal local) ; l'envoi par e-mail est écrit mais testé seulement avec un faux serveur de messagerie.
- Le téléchargement d'un justificatif par un administrateur a été testé en PHP, pas dans un navigateur.
- Pas d'écran de vérification ni d'administration des agences côté web (Phase 7) ; l'API existe.
- Les demandes (`inquiries`) n'existent pas encore : leur attribution (AGC-04) et les indicateurs associés arrivent en Phase 5.
- Un seul gérant par agence, pas de transfert de gérance.
- Logo : pas de test avec de très grandes images.
- Les documents ne sont jamais supprimés : durée de conservation à décider avec un juriste.
