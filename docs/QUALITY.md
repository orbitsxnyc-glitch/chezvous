# Qualité et tests : conventions minimales

Les outils ci-dessous sont les standards de chaque écosystème. Ils seront configurés à l'initialisation de chaque composant.

| Composant | Formatage / lint | Tests | Dépendances |
|---|---|---|---|
| `api/` | Laravel Pint, analyse statique à choisir | PHPUnit ou Pest | `composer audit` |
| `web/` | ESLint, Prettier | Tests unitaires et E2E à choisir | `npm audit` |
| `mobile/` | `dart format`, `flutter analyze` | `flutter test` | `flutter pub outdated` |

## Règles
- Chaque composant initialisé doit avoir au moins un test qui passe.
- Les résultats de tests ne sont rapportés que s'ils ont été exécutés.
- Vérifier avant chaque commit : `git status`, `git check-ignore` sur les fichiers sensibles, absence de secrets.
- CI/CD : à créer seulement quand les commandes de build et de test sont connues. Aucun résultat de pipeline ne sera simulé.
- Sauvegarde et restauration : procédure à concevoir avec l'hébergement choisi (voir `DECISIONS.md`).
