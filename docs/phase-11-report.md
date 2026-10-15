# Phase 11 : rapport (design Figma appliqué au site web)

Date : 2026-10-15. Branche `phase-11-design-web`, locale (non poussée). Référence : `docs/design-figma/`.

## Livré

- **Jetons du design** dans `globals.css` (palette, ombres) exposés comme couleurs Tailwind (`primary`, `sand`, `accent`, `ink`, `muted`, `line`, `success`, `warning`, `danger`, `info`…), **Manrope embarquée** (`next/font/local`, aucun téléchargement), thème clair forcé (le site s'affichait en sombre selon le réglage du système).
- **En-tête et pied de page communs** (`SiteChrome`) : logo, navigation (publique ou membre selon la connexion), boutons Se connecter / Créer un compte ou pastille du compte ; masqués sur `/admin`.
- **Accueil** refait : bandeau sable, recherche rapide (location/vente, quartier, budget → `/annonces` en paramètres d'URL, sans JavaScript), « À la une » étiquetée, quartiers, section « Pourquoi ChezVous ».
- **Composants** : carte d'annonce, badge de fraîcheur coloré et daté, Passeport du Bien, frais à l'entrée (avec « Aucun total n'est calculé par ChezVous »), champs et boutons.
- **Migration des couleurs** de toutes les pages existantes (zinc → jetons du design) et arrondis, pour une cohérence d'ensemble.

## Vérifié par exécution

- `npm run lint`, `typecheck`, Vitest 48/48, `next build` réussi ; les pages principales répondent 200 en développement ; le HTML rendu contient le nouvel accueil, les jetons CSS et la police.

## Limites connues (honnêtes)

- **Rendu visuel non vérifié par moi** : je n'ai pas de navigateur piloté ni de capture d'écran du Mac ; seuls le HTML, le CSS et le build sont vérifiés. L'aspect réel est à valider par vous.
- Seules les pages de premier plan ont été retravaillées à la main (accueil, en-tête, pied, cartes, fiche : Passeport et frais). Les autres pages (formulaires de compte, messagerie, abonnement, admin, éditeur) ont reçu les nouvelles couleurs mais **pas la mise en page du design** (stepper, bulles, listes en cartes, etc.).
- La page de résultats et la fiche d'annonce n'ont pas la mise en page complète du design (filtres latéraux, galerie en mosaïque, carte de contact collante).
- Pas de mode sombre ; pas de carte ; images de quartiers du design non reprises (pas de photos libres de droits fournies).
- Le logo (orange, rouge, jaune) et la palette du design (vert, terre cuite) restent à harmoniser (voir `DECISIONS.md`).
