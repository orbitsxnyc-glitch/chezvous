# Phase 13 : rapport (vérification du compte par e-mail, option B)

Date : 2026-10-17. Branche `phase-13-email-otp`, locale (non poussée). Elle part de `phase-12-admin`.

## Pourquoi

Le code de vérification n'était écrit que dans les journaux de l'API : en production, personne ne pouvait s'inscrire. Aucun fournisseur de SMS n'est choisi (compte et crédits payants requis). Décision du porteur : **option B**, le code part par e-mail en attendant les SMS.

## Livré

- **Code par e-mail** avec les mêmes protections qu'avant (durée, nombre d'essais, limitation de débit). Courriel reformulé (motif, mise en garde, « ignorez ce message »).
- **E-mail obligatoire à l'inscription** dès que le canal est l'e-mail (site, application et API ; message d'erreur explicite).
- **Un compte vérifié par e-mail n'a pas de numéro prouvé** : nouveau marqueur `users.verified_via` (`email`, `sms` ou vide pour les comptes antérieurs et le développement) et `User::hasProvenPhone()`.
  - Le compte est vérifié (connexion, publication, etc. inchangés).
  - **Le Passeport n'affiche aucun contrôle « téléphone »** pour un annonceur vérifié par e-mail (le contrôle reste pour les numéros prouvés).
  - Les canaux SMS et WhatsApp n'enverront que vers un numéro prouvé.
  - L'API expose `phone_proven` à côté de `phone_verified`.
- **Réinitialisation du mot de passe** par code e-mail : vérifie aussi le compte, sans prouver le numéro.
- **Contrôle de configuration** (`chezvous:preflight`) : avertissement `phone_unproven` tant que les codes partent par e-mail.
- Textes du site et de l'application : « Vérifier mon compte », code « reçu par e-mail », pense aux courriers indésirables.

## Vérifié par exécution

- Pest 87/87 sur l'authentification et la sécurité (dont 7 nouveaux tests : e-mail obligatoire, parcours complet, numéro non prouvé, Passeport, réinitialisation, absence d'énumération, protections du code).
- **Parcours réel sur l'API locale** : inscription refusée sans e-mail, courriel généré (écrit dans le journal local) avec le code, vérification, connexion (`phone_verified: true`, `phone_proven: false`). Compte de test supprimé.

## Limites connues (honnêtes)

- **Aucun e-mail réel n'a été envoyé** : le transport est le journal local. Pour la production il faut un service d'envoi (SMTP ou API, souvent gratuit au départ), un domaine d'envoi avec SPF/DKIM (sinon les codes finissent en courriers indésirables) et `MAIL_MAILER` réglé ; la commande de contrôle l'impose déjà.
- **Le numéro de téléphone n'est pas prouvé** : un annonceur peut déclarer le numéro de quelqu'un d'autre. Les visiteurs ne voient pas de contrôle « téléphone » pour lui, mais rien n'empêche l'usurpation tant qu'il n'y a pas de SMS.
- **E-mail déjà utilisé** : pour ne pas révéler qu'une adresse est inscrite, l'inscription répond comme pour un succès mais n'envoie pas de code ; la personne ne reçoit rien (limite connue de la protection contre l'énumération).
- Les comptes sans adresse (créés avant) ne peuvent plus recevoir de code ni réinitialiser leur mot de passe tant qu'ils n'en ont pas ajouté une.
- Une fois un fournisseur de SMS choisi : brancher l'envoi (`channel() = sms`), ce qui renseignera `verified_via = sms` et rétablira le contrôle « téléphone ».
