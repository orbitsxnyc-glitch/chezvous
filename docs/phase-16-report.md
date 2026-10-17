# Phase 16 — Refonte graphique (web + mobile)

Branche `phase-16-refonte`. Base : charte graphique et maquettes mobiles fournies par le porteur du projet.

## Livré
- **Marque (lot A)** : logo vectoriel (maison + repère), police Inter, palette de la charte, icônes, favicon.
- **Mobile (lots B, C)** : écran de bienvenue (une seule fois), Accueil (salutation, onglets Location/Vente, recherche, puces Budget/Chambres/Type/Filtres, quartiers populaires avec nombre de biens), Recherche (puces, « N biens trouvés », tri, carte), Filtres avancés, Fiche (galerie avec retour/favori/compteur, prix, disponibilité datée, carte Passeport, barre Message + Proposer une visite), écran Passeport (« Contrôles effectués »), Connexion (+221, œil mot de passe), Inscription (Particulier/Professionnel), Messages (avatar, aperçu du dernier message, non lus), Mon espace (compteurs, menu, publication).
- **Navigation mobile** : Accueil, Recherche, Favoris, Messages, Compte.
- **Web** : compteur « N biens trouvés » sur la recherche ; tokens de la charte, en-tête et pied de page, accueil avec quartiers populaires (dégradés, nombre de biens), accroche « Trouvez. Vivez. Avancez. ».
- **API** : `listings_count` par quartier (`with_counts=1`), `meta.total` sur la première page des résultats, `last_message` et `counterpart` sur les conversations (tests Pest ajoutés).

## Écarts assumés par rapport aux maquettes
| Maquette | Livré | Raison |
|---|---|---|
| Orange #FF6A00 pour les boutons | #C93F00 (interactif), #FF6A00 en décor | Blanc sur #FF6A00 = 2,87:1, sous le seuil WCAG AA |
| Connexion Google / Apple | Non livré | Comptes externes : accord requis |
| Photos de stock | Cartes en dégradé de marque | Pas de droits d'image |
| « Bien vérifié » | « Contrôles effectués », sans note globale | Règle 6 : un contrôle n'est pas une garantie juridique |
| « Réserver une visite » | « Proposer une visite » (ouvre la conversation) | La visite se convient avec l'annonceur |
| Courte durée | Non livré | Hors périmètre MVP |
| Passeport : onglets Documents / Historique | Une seule liste de contrôles datés | Pas de données correspondantes côté API |
| Fiche : salles de bain, parkings | Non affiché | Champs absents de l'API |
| Mon espace : compteur « visites » | Annonces / reçus / envoyés | Pas d'agrégat de visites |
| Publier : assistant en 4 étapes | Éditeur existant présenté en 4 étapes (Infos / Détails / Photos / Aperçu) avec Précédent / Suivant ; grille de types non reprise | Les sauvegardes restent par étape |
| Case « J'accepte les CGU » | Non livré | Pages juridiques non rédigées (à valider par un juriste) |
| Bouton partager | Non livré | Évite une dépendance supplémentaire |

## Reste à faire
- Fichiers d'origine du logo (SVG / PNG HD) pour remplacer la vectorisation manuelle.
- Refonte web des pages résultats, fiche et authentification à la même finesse que le mobile ; audit axe à relancer sur serveur démarré.
- Mot de passe oublié sur mobile, partage.
