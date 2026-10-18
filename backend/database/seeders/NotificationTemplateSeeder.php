<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Modèles de message en français (version 1). Idempotent : ne recrée pas une version existante.
 * Aucun modèle ne contient de coordonnées personnelles ; les variables sont celles déclarées dans config/notifications.php.
 */
class NotificationTemplateSeeder extends Seeder
{
    /** type => [objet de l'e-mail, texte de l'e-mail, texte court (push, WhatsApp, SMS)] */
    private const TEXTS = [
        'security.otp' => ['Votre code ChezVous', 'Votre code de vérification a été envoyé séparément. Ne le communiquez à personne.', 'Code de vérification ChezVous envoyé. Ne le partagez pas.'],
        'security.password_changed' => ['Votre mot de passe a été modifié', 'Le mot de passe de votre compte ChezVous vient d\'être modifié. Si ce n\'est pas vous, contactez-nous immédiatement.', 'Mot de passe ChezVous modifié. Pas vous ? Contactez-nous.'],
        'security.two_factor_changed' => ['Double authentification modifiée', 'La double authentification de votre compte ChezVous vient d\'être activée, réinitialisée ou un code de secours a été utilisé. Si ce n\'est pas vous, contactez immédiatement un super administrateur.', 'Double authentification ChezVous modifiée. Pas vous ? Alertez un super administrateur.'],
        'security.account_suspended' => ['Votre compte est suspendu', 'Votre compte ChezVous a été suspendu par notre équipe. Contactez le support pour en connaître la raison.', 'Compte ChezVous suspendu. Contactez le support.'],
        'moderation.warning' => ['Avertissement concernant votre annonce {listing}', 'Votre annonce {listing} a fait l\'objet d\'un signalement. Message de la modération : {reason}. Merci de la corriger pour rester conforme.', 'Avertissement sur {listing} : {reason}.'],
        'listing.approved' => ['Votre annonce {listing} est en ligne', 'Votre annonce {listing} a été approuvée et est maintenant visible sur ChezVous.', 'Annonce {listing} approuvée et en ligne.'],
        'listing.rejected' => ['Votre annonce {listing} a été refusée', 'Votre annonce {listing} n\'a pas pu être publiée. Motif : {reason}. Corrigez-la puis soumettez-la à nouveau.', 'Annonce {listing} refusée : {reason}.'],
        'listing.hidden' => ['Votre annonce {listing} a été masquée', 'Votre annonce {listing} a été masquée par notre équipe de modération. Consultez votre espace pour en savoir plus.', 'Annonce {listing} masquée par la modération.'],
        'listing.expiring' => ['Votre annonce {listing} expire bientôt', 'Votre annonce {listing} arrive à expiration. Confirmez sa disponibilité pour la garder visible.', 'Annonce {listing} bientôt expirée.'],
        'inquiry.new' => ['Nouvelle demande pour {listing}', 'Vous avez reçu une nouvelle demande de contact pour l\'annonce {listing}. Répondez depuis votre boîte de réception.', 'Nouvelle demande pour {listing}.'],
        'inquiry.message' => ['Nouveau message ({listing})', 'Vous avez un nouveau message concernant l\'annonce {listing}.', 'Nouveau message pour {listing}.'],
        'inquiry.status' => ['Mise à jour de votre demande ({listing})', 'Le statut de votre demande pour l\'annonce {listing} a changé.', 'Demande {listing} mise à jour.'],
        'inquiry.assigned' => ['Une demande vous est attribuée ({listing})', 'Une demande pour l\'annonce {listing} vous a été attribuée.', 'Demande {listing} attribuée.'],
        'inquiry.unanswered' => ['Demande sans réponse ({listing})', 'Une demande pour l\'annonce {listing} attend votre réponse depuis plus de 48 heures.', 'Demande {listing} sans réponse (+48 h).'],
        'visit.proposed' => ['Proposition de visite ({listing})', 'Une visite vous est proposée pour l\'annonce {listing}. Choisissez un créneau.', 'Visite proposée pour {listing}.'],
        'visit.confirmed' => ['Visite confirmée ({listing})', 'La visite de l\'annonce {listing} est confirmée.', 'Visite {listing} confirmée.'],
        'visit.rescheduled' => ['Visite à reprogrammer ({listing})', 'De nouveaux créneaux sont proposés pour la visite de l\'annonce {listing}.', 'Visite {listing} à reprogrammer.'],
        'visit.cancelled' => ['Visite annulée ({listing})', 'La visite de l\'annonce {listing} a été annulée.', 'Visite {listing} annulée.'],
        'visit.reminder' => ['Rappel : visite dans {hours} h ({listing})', 'Rappel : votre visite de l\'annonce {listing} a lieu le {when}.', 'Rappel : visite {listing} le {when}.'],
        'visit.done' => ['Visite effectuée ({listing})', 'Votre visite de l\'annonce {listing} est marquée comme effectuée. Donnez votre retour.', 'Visite {listing} effectuée : donnez votre retour.'],
        'visit.feedback' => ['Retour reçu ({listing})', 'Le visiteur a donné son retour après la visite de l\'annonce {listing}.', 'Retour reçu pour {listing}.'],
        'favorite.price_drop' => ['Baisse de prix : {listing}', 'Le prix d\'un de vos favoris ({listing}) a baissé : {old_price} puis {new_price}.', 'Baisse de prix sur {listing} : {new_price}.'],
        'favorite.unavailable' => ['Un favori n\'est plus disponible', 'L\'annonce {listing}, dans vos favoris, n\'est plus disponible.', 'Favori {listing} indisponible.'],
        'search.alert' => ['Nouvelles annonces : {name}', '{count} nouvelle(s) annonce(s) correspondent à votre recherche « {name} ».', '{count} nouvelle(s) annonce(s) pour « {name} ».'],
        'booking.requested' => ['Nouvelle demande de séjour ({unit})', 'Un voyageur demande à séjourner dans {unit}. Répondez avant l\'échéance depuis votre espace hôte.', 'Nouvelle demande de séjour : {unit}.'],
        'booking.accepted' => ['Votre demande est acceptée ({unit})', 'L\'hôte a accepté votre demande pour {unit}. Les dates sont réservées pour vous le temps de régler.', 'Demande acceptée : {unit}.'],
        'booking.declined' => ['Votre demande n\'a pas été retenue ({unit})', 'L\'hôte ne peut pas accueillir votre séjour dans {unit}. Aucun montant n\'a été prélevé.', 'Demande refusée : {unit}.'],
        'booking.expired' => ['Demande expirée ({unit})', 'La demande de séjour pour {unit} a expiré. Les dates sont de nouveau libres.', 'Demande expirée : {unit}.'],
        'booking.cancelled' => ['Séjour annulé ({unit})', 'Le séjour dans {unit} a été annulé. Consultez votre espace pour le détail.', 'Séjour annulé : {unit}.'],
        'booking.confirmed' => ['Séjour confirmé ({unit})', 'Votre séjour dans {unit} est confirmé.', 'Séjour confirmé : {unit}.'],
        'booking.disputed' => ['Litige ouvert ({unit})', 'Un litige a été ouvert sur le séjour dans {unit}. Notre équipe l\'examine ; vous serez informé de la décision.', 'Litige ouvert : {unit}.'],
        'booking.completed' => ['Séjour terminé ({unit})', 'Votre séjour dans {unit} est terminé. Merci d\'avoir choisi ChezVous.', 'Séjour terminé : {unit}.'],
        'booking.resolved' => ['Litige résolu ({unit})', 'Le litige sur le séjour dans {unit} a été tranché. Consultez votre espace pour la décision.', 'Litige résolu : {unit}.'],
        'unit.approval_revoked' => ['Votre logement n\'est plus publié ({unit})', 'Le logement {unit} a été retiré de la publication : un contrôle exigé a expiré ou n\'est plus valide. Contactez-nous pour le renouveler.', 'Logement retiré : {unit}.'],
        'subscription.requested' => ['Nouvelle demande de formule', 'Une demande de formule attend un encaissement et une activation.', 'Nouvelle demande de formule.'],
        'subscription.activated' => ['Votre formule {plan} est activée', 'Votre formule {plan} est active jusqu\'au {ends_at}.', 'Formule {plan} activée jusqu\'au {ends_at}.'],
        'subscription.suspended' => ['Votre formule est suspendue', 'Votre formule a été suspendue. Vos droits sont revenus à la formule gratuite.', 'Formule suspendue.'],
        'subscription.expiring' => ['Votre formule expire dans {days} jour(s)', 'Votre formule expire le {ends_at}. Renouvelez-la pour conserver vos droits.', 'Formule : expiration le {ends_at}.'],
        'subscription.expired' => ['Votre formule a expiré', 'Votre formule a expiré. Choisissez les annonces à garder avant le {grace_until}, les plus anciennes seront ensuite mises en pause.', 'Formule expirée. Choisissez vos annonces avant le {grace_until}.'],
        'subscription.downgrade_enforced' => ['Des annonces ont été mises en pause', '{listings_paused} annonce(s) ont été mises en pause car elles dépassent le quota de votre formule. Elles ne sont pas supprimées.', '{listings_paused} annonce(s) mises en pause (quota).'],
        'premium.requested' => ['Nouvelle demande de mise en avant', 'Une demande de mise en avant ({listing}) attend un encaissement et une activation.', 'Nouvelle demande de mise en avant.'],
        'premium.activated' => ['Mise en avant activée ({listing})', 'La mise en avant de l\'annonce {listing} est active jusqu\'au {ends_at}.', 'Mise en avant {listing} jusqu\'au {ends_at}.'],
        'premium.expired' => ['Mise en avant terminée ({listing})', 'La mise en avant de l\'annonce {listing} est terminée.', 'Mise en avant {listing} terminée.'],
    ];

    public function run(): void
    {
        foreach (array_keys(config('notifications.types')) as $type) {
            [$subject, $long, $short] = self::TEXTS[$type] ?? ["Notification ChezVous ({$type})", 'Vous avez une nouvelle notification sur ChezVous.', 'Nouvelle notification ChezVous.'];
            foreach (config('notifications.channels') as $channel) {
                NotificationTemplate::firstOrCreate(
                    ['type' => $type, 'channel' => $channel, 'locale' => 'fr', 'version' => 1],
                    ['subject' => $channel === 'email' ? $subject : null, 'body' => $channel === 'email' ? $long : $short, 'is_active' => true],
                );
            }
        }
    }
}
