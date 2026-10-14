# Phase 3 : rapport (recherche, fiche publique, Passeport, fraîcheur)

Date : 2026-10-16. Branche `phase-3-recherche`, locale.

## Livré

- **Fraîcheur :** `FreshnessService` (seuils 48 h et 7 jours en configuration), historique des confirmations, rang dénormalisé recalculé chaque heure, confirmation par l'annonceur (`POST /my/listings/{id}/availability`). La mise en ligne vaut première confirmation.
- **API publique :** `GET /listings` (filtres, tri, curseur), `/listings/map` (emprise), `/listings/{ref|ulid}`, `/listings/{id}/similar`, `/passports/{ref}`, `/seo/sitemap`.
- **Confidentialité :** aucun téléphone, e-mail, adresse exacte ni position exacte dans les réponses publiques ; position approximative décalée de façon constante ; recherche par zone sur la position publique.
- **Compteurs de vues** dédoublonnés par visiteur et par jour (clé HMAC, jamais l'IP), écrits en base par lots ; compteur de contacts prêt pour la Phase 5.
- **Site public Next.js :** recherche, fiche avec Passeport et coût d'entrée, pages SEO `/a-louer` et `/a-vendre`, sitemap, robots, Open Graph, JSON-LD.
- **Outils locaux :** `demo:listings` (données fictives, local uniquement) et `search:benchmark`.

## Vérifié par exécution

- `make check` : code 0 (Pest 169/169, PHPStan 0 erreur, Pint, Vitest 30/30, lint, typecheck, build Next.js sans API, Flutter).
- **Performance** sur 8 985 annonces publiées (10 000 fictives) : pire p95 **48,5 ms** (cible 400 ms). Mesure locale, séquentielle, pile HTTP sans réseau.
- Pages servies avec la vraie API : 200 sur la recherche, la fiche et les pages SEO ; `noindex` sur les pages inconnues.

## Limites connues

- Pas d'interface cartographique (API prête).
- Pages inexistantes : 200 + `noindex` (pas de 404 HTTP) à cause du rendu en flux.
- Photos des annonces fictives absentes (chemins factices).
- Les contrôles du Passeport (identité, document, visite terrain…) ne sont alimentés par aucun écran : back-office en Phase 7.
- Boutons Appeler, WhatsApp et demande de visite : Phase 5.
- La recherche texte n'a pas d'index trigramme (suffisant à 10 000 annonces).
- Charge concurrente non testée.
