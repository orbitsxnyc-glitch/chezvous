# Registre des décisions

## Décisions prises

| Sujet | Décision |
|---|---|
| Backend | Laravel (pas de NestJS sans autorisation explicite) |
| Marque | ChezVous, provisoire |
| Web | Next.js |
| Mobile | Flutter |
| Base de données | PostgreSQL + PostGIS |
| Architecture | Monolithe modulaire, API REST |
| Recherche initiale | PostgreSQL et recherche géographique, sans moteur externe |
| Dépôt | Monorepo (`api/ web/ mobile/ docs/ infra/`) |
| Canaux de notification ciblés | WhatsApp, SMS, push |
| Périmètre de lancement | Location résidentielle à Dakar |

## Questions ouvertes

| # | Sujet | Recommandation | Impact |
|---|---|---|---|
| 1 | Nom définitif, identité visuelle, domaine | Décider avant la mise en ligne publique | Marque, SEO, stores |
| 2 | Versions exactes des frameworks | Vérifier la compatibilité avant installation | Stabilité du démarrage |
| 3 | Hébergement, région, résidence des données | Choisir après avis juridique | Latence, conformité, coût |
| 4 | Environnement local : natif ou Docker | Natif (Homebrew), disque limité et Docker absent | Temps de mise en place |
| 5 | Fournisseur OTP/SMS, connexion par téléphone | Comparer coûts et couverture Sénégal | Inscription, coûts récurrents |
| 6 | Prestataire WhatsApp Business et coût des messages | Valider avant toute conception de relances | Coût, modèles de messages |
| 7 | Fournisseur push et config mobile | FCM probable, à confirmer | Comptes Apple/Google |
| 8 | Stockage compatible S3 | Comparer région et coût | Médias, sauvegardes |
| 9 | Cartographie et fournisseur géographique | À comparer (coût, couverture de Dakar) | Recherche par carte |
| 10 | Langues de lancement | Français d'abord | Volume de contenu |
| 11 | Précision de localisation publique | Zone approximative avant contact | Vie privée, sécurité |
| 12 | Contrôle des identités, mandats, documents | Manuel au MVP | Charge opérationnelle |
| 13 | Conservation des données, validation juridique | Avis d'un juriste avant lancement | Conformité |
| 14 | Règles de calcul et d'affichage des frais | Valider le cadre légal avant automatisation | Transparence des prix |
| 15 | Git distant et stratégie de branches | Branche `main` protégée, branches courtes | Collaboration, CI |
| 16 | Modération : processus et responsabilités | Définir une file et des rôles | Qualité de l'inventaire |
| 17 | Emplacement de Flutter (actuellement dans `~/Downloads`) | Le déplacer vers un emplacement stable | Risque de perte de l'outil |
| 18 | Cahier des charges détaillé | Le placer dans `docs/` | Écarts non évaluables sans lui |
