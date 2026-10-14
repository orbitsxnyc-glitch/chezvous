# Addendum aux spécifications MVP : Passeport du Bien

Statut : **Décision** du porteur du projet (2026-10-14). Cet addendum complète `docs/specs-mvp.md` et prime sur lui en cas de conflit sur le modèle de données.

## Motif

Les spécifications v1 utilisent une seule table `properties` qui mélange le bien et l'annonce, et n'ont ni mandats, ni contrôles de vérification, ni fraîcheur de disponibilité. La présentation de référence fait du Passeport du Bien la fonction signature.

## Découpage retenu

| Table | Rôle | Remarque |
|---|---|---|
| `properties` | Le bien physique : référence unique de type DK-YOF-002458, type, surface, pièces, localité, localisation, attributs | Existe une seule fois, même avec plusieurs annonces |
| `listings` | L'annonce : annonceur, transaction, prix, frais, titre, description, statut du cycle de vie, publication, expiration, compteurs | Reprend le cycle de vie « Annonce » de la section 5 des spécifications |
| `mandates` | Lien d'un annonceur à un bien (propriétaire, mandataire, intermédiaire) avec document et dates | Document en stockage privé |
| `verification_checks` | Contrôle explicite, daté, rejouable : téléphone, identité, professionnel, localisation, document, visite terrain | Jamais une note globale ; historique conservé |
| `availability_confirmations` | Confirmation de disponibilité : réponse (disponible, réservé, loué, retiré), date, auteur, canal | Alimente la fraîcheur et le classement |

Les tables `property_images`, `property_amenity`, `favorites`, `inquiries` etc. s'attachent à `listings` ou `properties` selon leur sens (photos et équipements au bien ; favoris et demandes à l'annonce). Les identifiants publics restent des ULID, la référence lisible du bien est distincte de celle de l'annonce.

## Fraîcheur (règle de principe)

Les états Disponible, À reconfirmer et Information ancienne sont calculés à partir de la dernière `availability_confirmation`, avec des seuils **en configuration** (jamais en dur). Plus l'information vieillit, moins l'annonce est mise en avant. Une relance de confirmation passe par le système de notifications.

## Périmètre

Le MVP et le pilote ciblent la **location résidentielle à Dakar**. Les champs pour la vente, les terrains et les locations meublées courte durée sont modélisés mais ne sont pas livrés au pilote.

## Phasage

Le module Passeport (`properties` / `listings` séparés, `mandates`, `verification_checks`, `availability_confirmations`) est intégré à la **Phase 2**. L'interface de fraîcheur et de classement est livrée avec la Phase 3 (recherche). Le calcul du total des frais d'entrée attend la validation des règles légales (voir `DECISIONS.md`).

## Hors périmètre

La détection automatique de doublons reste en V2, comme dans la présentation.

## Mention juridique

Un contrôle « document contrôlé » ne garantit pas la propriété juridique. Cette mention doit apparaître dans l'interface et les CGU.
