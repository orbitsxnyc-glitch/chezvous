# Checklist de sécurité initiale

Case cochée = vérifié. Aucune case n'est cochée en Phase 0 : rien n'est encore implémenté, sauf mention.

> Cette checklist ne vaut pas validation de conformité. La conformité au droit sénégalais de la protection des données doit être vérifiée par un juriste.

## Secrets et environnement
- [ ] Aucun `.env` réel versionné ; un `.env.example` sans valeur secrète
- [ ] `.gitignore` couvre clés, certificats, fichiers de services Firebase
- [ ] Secrets de production hors du dépôt
- [ ] Analyse de secrets avant chaque commit ou en CI

## Authentification et autorisations
- [ ] Connexion sécurisée (OTP téléphone) avec limitation de débit
- [ ] Rôles : chercheur, propriétaire, agent, agence, modérateur, admin
- [ ] Policies côté serveur sur chaque ressource
- [ ] Séparation stricte des données entre agences, testée automatiquement

## Données entrantes
- [ ] Validation systématique par Form Requests
- [ ] Protection contre l'injection SQL et XSS
- [ ] Limitation de débit sur les routes publiques

## Fichiers et documents
- [ ] Contrôle du type MIME réel et de la taille
- [ ] Suppression des métadonnées EXIF (GPS)
- [ ] Documents privés hors espace public, accès par URL signée courte
- [ ] Aucun justificatif d'identité dans Git

## Journalisation
- [ ] Aucun mot de passe, jeton, OTP ni document dans les logs
- [ ] Numéros de téléphone masqués dans les logs

## Dépendances
- [ ] `composer audit` et `npm audit` exécutés régulièrement
- [ ] Versions verrouillées (lockfiles versionnés)

## Sauvegardes
- [ ] Sauvegardes automatiques de PostgreSQL et du stockage
- [ ] Restauration testée au moins une fois

## Données personnelles
- [ ] Consentement pour notifications WhatsApp/SMS
- [ ] Politique de conservation définie
- [ ] Avis juridique sur la conformité au Sénégal
