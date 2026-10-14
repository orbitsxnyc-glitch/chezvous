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
| Périmètre de lancement | Location résidentielle à Dakar (les autres types sont modélisés, non livrés au pilote) |
| Passeport du Bien | Dans le MVP, avec séparation `properties` / `listings` (voir `specs-addendum-passeport.md`) |
| Nom du dossier backend | `backend/` (conforme à `specs-mvp.md`) |
| Spécifications | `docs/specs-mvp.md` fait référence, corrigé par l'addendum Passeport |

## Questions ouvertes

| # | Sujet | Recommandation | Impact |
|---|---|---|---|
| 1 | Nom définitif, identité visuelle, domaine | Décider avant la mise en ligne publique | Marque, SEO, stores |
| 2 | Versions exactes des frameworks | Vérifier la compatibilité avant installation | Stabilité du démarrage |
| 3 | Hébergement, région, résidence des données | Choisir après avis juridique | Latence, conformité, coût |
| 4 | Environnement local : natif ou Docker | Le porteur dit avoir Docker Desktop, mais il n'est pas détecté sur la machine (2026-10-14). PostgreSQL/PostGIS installés en natif ; docker-compose à ajouter quand Docker fonctionne | Temps de mise en place, CI |
| 19 | Redis et CI GitHub Actions | Redis en natif ou via docker-compose ; CI à créer après création du dépôt distant par le porteur | Files, cache, qualité continue |
| 20 | Pièces de vérification, tarifs, quotas, mises en avant, WhatsApp/SMS, CGU/CDP, hébergement VPS, calendrier mobile, comptes développeur | Voir `specs-mvp.md` section 11 (valeurs provisoires) | Phases 4 à 8 |
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
