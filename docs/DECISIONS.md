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

## Décisions déléguées au 2026-10-14

Le porteur du projet a délégué à Claude les décisions recommandées. Elles sont réversibles. Ne sont **pas** couverts par cette délégation : la création de comptes externes, les services payants, les pushs vers le dépôt distant et la validation juridique.

| Sujet | Décision retenue | Réversibilité |
|---|---|---|
| Base de la Phase 2 | Branche `phase-2-annonces` créée depuis `phase-1-comptes` ; rebase sur `main` après fusion de la pull request | Facile |
| Types et transactions | Tous modélisés (spec ANN-02/03) ; seule la location résidentielle est testée et livrée au pilote | Facile |
| Localités | Liste du lancement (Yoff, Ouakam, Mamelles, Almadies, Ngor, Mermoz, Sacré-Cœur, VDN, Keur Gorgui, Liberté) sans contours précis au départ | Facile (seeders) |
| Stockage des photos | Disque local en développement, S3-compatible à choisir avant la recette | Facile (config) |
| Écrans web de publication | Après la maquette Figma ; la Phase 2 livre l'API et la modération côté API | Facile |
| Environnement local | Natif (PostgreSQL 17, Redis via Homebrew) ; `docker-compose` conservé pour la CI | Facile |
| Hébergement (recommandation) | VPS Linux unique en recette (spec H12) ; fournisseur et région à choisir par le porteur | À confirmer |
| OTP/SMS (recommandation) | Agrégateur SMS local avec contrat Sénégal ; e-mail en attendant ; choix du fournisseur par le porteur | À confirmer |
| WhatsApp (recommandation) | API WhatsApp Business officielle, via un prestataire ; coût à valider | À confirmer |
| Push | FCM ; comptes Google et Apple à créer par le porteur | À confirmer |
| Langues | Français au lancement ; i18n prête pour l'anglais et le wolof | Facile |
| Précision GPS publique | Position approximative par défaut (zone), exacte après contact | Facile (config) |
| Vérification des agences | NINEA + RCCM (spec H8) | Facile |
| Vérification des particuliers | Téléphone uniquement au MVP | Facile |
| Branches Git | `main` protégée, une branche par phase, pull request avant fusion | Facile |
| Fraîcheur | États Disponible / À reconfirmer / Information ancienne, seuils en configuration (valeurs initiales : 48 h et 7 jours) | Facile (config) |
| Compte « de confiance » (H7) | Téléphone vérifié et au moins 1 annonce approuvée par un modérateur (`trusted_after_approvals`) : ses annonces suivantes sont publiées sans modération, mais restent signalables | Facile (config) |
| Quota particulier | 3 annonces actives simultanées (publiées + en modération), configurable ; remplacé par les droits des formules en Phase 6 | Facile (config) |
| Routes d'annonces | `/my/listings` (et non `/my/properties`) : la ressource gérée par l'annonceur est l'annonce ; le bien (`properties`) est le Passeport | Facile |
| Photos | Fichier brut gardé en privé le temps du traitement, puis supprimé ; WebP en 3 tailles ; EXIF retiré par réencodage ; antivirus non disponible | À revoir avant la production |
| Duplication | Copie la fiche en nouveau bien et nouveau brouillon, sans photos | Facile |
| Biens à information ancienne | Rétrogradés en bas de liste (pas masqués) pendant le pilote ; `CHEZVOUS_HIDE_STALE=true` pour les masquer | Facile (config) |
| Retrait d'un bien | « Loué » ferme l'annonce ; « Retiré » la met en pause (réversible) ; « Réservé » la laisse en ligne avec le badge | Facile |
| Position approximative | Décalage fixe par bien (40 à 100 % de 300 m, configurable) ; la recherche par zone s'appuie sur la position publique uniquement | Facile (config) |
| Coût d'entrée | Montants saisis affichés sans total calculé, avec avertissement ; calcul automatique après validation des règles légales | À décider |
| Carte | API `/listings/map` prête ; interface cartographique non livrée (fournisseur de tuiles à valider, MapLibre/OpenStreetMap recommandé) | À décider |
| Pages inexistantes | Statut HTTP 200 avec `noindex` automatique (contrainte du rendu en flux de Next.js), pas de 404 réel | À revoir |
| URL publiques | `/annonces/{référence}`, `/a-louer/{type}/{quartier}`, `/a-vendre/{type}/{quartier}` | Facile |
| Quota d'une agence | 50 annonces actives simultanées pour toute l'équipe (`CHEZVOUS_QUOTA_AGENCY`), indépendant du quota personnel ; remplacé par la formule en Phase 6 | Facile (config) |
| Droit étendu d'un agent | Accordé et retiré par le gérant uniquement : permet de VOIR toute l'agence, jamais de modifier les annonces d'autrui | Facile |
| Une agence par compte | Un compte n'appartient qu'à une seule agence (contrainte en base) ; un seul gérant par agence au MVP | À revoir |
| Confiance d'une agence | Une agence vérifiée (ou ayant déjà une annonce approuvée) publie sans modération ; le téléphone de l'annonceur doit être vérifié | Facile (config) |
| Invitation | Jeton aléatoire de 48 caractères, stocké haché, valable 7 jours, à usage unique ; invitation par téléphone liée au titulaire du numéro ; envoi réel (SMS) en attente du fournisseur, journal local en développement | À confirmer |
| Justificatifs | NINEA et RCCM en stockage privé, lien signé de 5 minutes, administrateur connecté obligatoire ; aucune durée de conservation décidée (avis juridique) | À décider |
| Annonceur public d'une annonce d'agence | L'agence est présentée, jamais l'agent (ni nom, ni téléphone, ni e-mail) | Facile |
| Agence suspendue | Ses annonces disparaissent de la recherche et de la fiche publique ; sa page répond 404 ; aucun écran d'administration avant la Phase 7 | À revoir |

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
