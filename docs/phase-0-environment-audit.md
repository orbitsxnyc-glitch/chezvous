# Phase 0 : audit de l'environnement

Date : 2026-10-14. Audit en lecture seule, avant toute installation.

## Faits vérifiés

- Système : macOS 26.6.2, arm64, zsh.
- Disque : 14 Go libres (94 % utilisé). À surveiller.
- Le dossier ne contenait que la présentation .pptx. Aucun dépôt Git.

| Outil | État |
|---|---|
| Git | 2.54.0 |
| PHP | 8.4.25, extensions `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `intl`, `gd`, `bcmath`, `ctype`, `fileinfo`, `tokenizer`, `xml`, `zip`, `curl` présentes |
| Composer | 2.10.3 |
| Node.js / npm | 26.7.0 / 11.19.0 |
| pnpm, yarn | absents |
| Flutter / Dart | 3.47.2 / 3.13.2, situés dans `~/Downloads/orbitsxnyc-main/flutter` |
| Xcode / CocoaPods | 27.0 / présent |
| Android | outils en ligne de commande dans `/opt/homebrew/share/android-commandlinetools`, `ANDROID_HOME` défini |
| Homebrew | 7.0.9 (`postgresql@17` 17.11 et `postgis` 3.6.4 disponibles) |
| Docker | absent |
| PostgreSQL / PostGIS | absents, rien n'écoute sur le port 5432 |

## Risques

1. PostgreSQL et PostGIS doivent être installés avant tout travail sur le modèle de données.
2. Flutter se trouve dans `~/Downloads`, un emplacement fragile.
3. `flutter doctor` n'a pas été lancé : la chaîne iOS et Android n'est pas vérifiée.
4. Espace disque limité pour `node_modules`, builds iOS et SDK.
5. Compatibilité exacte des versions Laravel et Next.js avec PHP 8.4 et Node 26 non vérifiée.

## Mouvements effectués à l'étape B

- `git init` (branche `main`), création de `backend/ web/ mobile/ docs/ infra/`.
- Déplacement et renommage du .pptx vers `docs/ChezVous_Presentation_Concept_Immobilier_Senegal.pptx` (espaces remplacés par des underscores).
