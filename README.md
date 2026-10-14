# ChezVous (nom provisoire)

Plateforme immobilière de confiance pour le marché sénégalais, avec un lancement ciblé sur la **location résidentielle à Dakar**.

> Principe directeur : mieux vaut 500 biens réellement disponibles et fiables que 20 000 annonces obsolètes.

**État actuel : Phase 0 (fondations).** Aucune fonctionnalité métier n'est développée. Les dossiers applicatifs sont vides en attendant la validation de la Phase 0.

## Structure du monorepo

| Dossier | Contenu prévu |
|---|---|
| `api/` | API REST Laravel (monolithe modulaire) |
| `web/` | Next.js : site public, espace professionnel, back-office |
| `mobile/` | Application Flutter (Android et iOS) |
| `docs/` | Architecture, décisions, audit, sécurité, présentation source |
| `infra/` | Configuration d'infrastructure, si nécessaire |

## Documentation

- [Audit de l'environnement](docs/phase-0-environment-audit.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Décisions et questions ouvertes](docs/DECISIONS.md)
- [Checklist de sécurité](docs/SECURITY-CHECKLIST.md)
- [Qualité et tests](docs/QUALITY.md)
- Présentation source : `docs/ChezVous_Presentation_Concept_Immobilier_Senegal.pptx`

## Démarrage

Les commandes de démarrage seront documentées ici lorsque chaque composant aura été initialisé. Aucun composant n'est encore installé.

## Règles du dépôt

- Ne jamais versionner de fichier `.env` réel, de clé privée, de justificatif ou de document d'identité. Seuls les fichiers `.env.example` sont autorisés.
- Aucun envoi vers un dépôt distant sans accord du porteur du projet.
