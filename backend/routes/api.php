<?php

use App\Http\Controllers\Api\V1\Admin\AgencyAdminController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\CommercialController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\ModerationController;
use App\Http\Controllers\Api\V1\Admin\NotificationAdminController;
use App\Http\Controllers\Api\V1\Admin\ReferentialController;
use App\Http\Controllers\Api\V1\Admin\ReportAdminController;
use App\Http\Controllers\Api\V1\Admin\UserAdminController;
use App\Http\Controllers\Api\V1\Admin\VerificationController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AgencyTeamController;
use App\Http\Controllers\Api\V1\AgencyVerificationController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Commercial\CatalogController;
use App\Http\Controllers\Api\V1\Commercial\PremiumController;
use App\Http\Controllers\Api\V1\Commercial\SubscriptionController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\Engagement\FavoriteController;
use App\Http\Controllers\Api\V1\Engagement\InquiryController;
use App\Http\Controllers\Api\V1\Engagement\NotificationController;
use App\Http\Controllers\Api\V1\Engagement\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Engagement\PushDeviceController;
use App\Http\Controllers\Api\V1\Engagement\SavedSearchController;
use App\Http\Controllers\Api\V1\Engagement\VisitController;
use App\Http\Controllers\Api\V1\ListingReportController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\My\ListingAvailabilityController;
use App\Http\Controllers\Api\V1\My\ListingController;
use App\Http\Controllers\Api\V1\My\ListingImageController;
use App\Http\Controllers\Api\V1\PassportController;
use App\Http\Controllers\Api\V1\PublicAgencyController;
use App\Http\Controllers\Api\V1\PublicListingController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\SitemapController;
use Illuminate\Support\Facades\Route;

// Préfixe /api/v1 défini dans bootstrap/app.php.
Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class)->middleware('throttle:register');
    Route::post('otp/send', [OtpController::class, 'send'])->middleware('throttle:otp');
    Route::post('otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify');
    Route::post('password/reset', PasswordResetController::class)->middleware('throttle:otp-verify');
    Route::post('login', [SessionController::class, 'login'])->middleware('throttle:login');
    Route::post('logout', [SessionController::class, 'logout'])->middleware('auth:sanctum');
    Route::middleware('auth:sanctum')->prefix('2fa')->group(function () {
        Route::get('status', [TwoFactorController::class, 'status']);
        Route::post('setup', [TwoFactorController::class, 'setup'])->middleware('throttle:two-factor');
        Route::post('confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:two-factor');
        Route::post('verify', [TwoFactorController::class, 'verify'])->middleware('throttle:two-factor');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);
    Route::delete('me', [MeController::class, 'destroy']);
    Route::get('me/devices', [DeviceController::class, 'index']);
    Route::delete('me/devices/{id}', [DeviceController::class, 'destroy'])->whereNumber('id');
});

// Référentiels publics (ANN-02, RCH-01).
Route::get('localities', [ReferenceController::class, 'localities']);
Route::get('property-types', [ReferenceController::class, 'propertyTypes']);
Route::get('amenities', [ReferenceController::class, 'amenities']);

// Recherche et consultation publiques (RCH-01 à RCH-05). `listings/map` doit précéder `listings/{identifier}`.
Route::middleware('throttle:search')->group(function () {
    Route::get('listings', [PublicListingController::class, 'index']);
    Route::get('listings/map', [PublicListingController::class, 'map']);
    Route::get('listings/{identifier}', [PublicListingController::class, 'show'])->where('identifier', '[A-Za-z0-9-]+');
    Route::get('listings/{identifier}/similar', [PublicListingController::class, 'similar'])->where('identifier', '[A-Za-z0-9-]+');
    Route::get('seo/sitemap', SitemapController::class);
    Route::get('plans', [CatalogController::class, 'plans']);
    Route::get('premium-products', [CatalogController::class, 'products']);
    Route::get('featured', [CatalogController::class, 'featured']);
    Route::get('agencies/{slug}', [PublicAgencyController::class, 'show'])->where('slug', '[a-z0-9-]+');
    Route::get('passports/{reference}', [PassportController::class, 'show'])->where('reference', '[A-Za-z0-9-]+');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('listings/{listing}/reports', [ListingReportController::class, 'store'])->middleware('throttle:reports');

    // Demandes, messagerie, visites (DEM-01 à DEM-06). {inquiry} et {visit} se résolvent par ULID ; la Policy répond 404 aux tiers.
    Route::post('listings/{listing}/inquiries', [InquiryController::class, 'store'])->middleware('throttle:inquiries')->where('listing', '[A-Za-z0-9-]+');
    Route::get('my/inquiries', [InquiryController::class, 'sent']);
    Route::get('inbox', [InquiryController::class, 'inbox']);
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show']);
    Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update']);
    Route::post('inquiries/{inquiry}/messages', [InquiryController::class, 'message'])->middleware('throttle:messages');
    Route::post('inquiries/{inquiry}/notes', [InquiryController::class, 'note']);
    Route::post('inquiries/{inquiry}/visits', [VisitController::class, 'store']);
    Route::patch('visits/{visit}', [VisitController::class, 'update']);
    Route::post('visits/{visit}/feedback', [VisitController::class, 'feedback']);

    // Favoris, recherches enregistrées, notifications (FAV-01, FAV-02, NOT-01 in-app).
    Route::get('my/favorites', [FavoriteController::class, 'index']);
    Route::post('my/favorites', [FavoriteController::class, 'store']);
    Route::delete('my/favorites/{listing}', [FavoriteController::class, 'destroy']);
    Route::get('my/saved-searches', [SavedSearchController::class, 'index']);
    Route::post('my/saved-searches', [SavedSearchController::class, 'store']);
    Route::patch('my/saved-searches/{search}', [SavedSearchController::class, 'update']);
    Route::delete('my/saved-searches/{search}', [SavedSearchController::class, 'destroy']);
    Route::get('my/notifications', [NotificationController::class, 'index']);
    Route::patch('my/notifications/read', [NotificationController::class, 'read']);
    Route::get('my/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::patch('my/notification-preferences', [NotificationPreferenceController::class, 'update']);
    Route::get('my/devices', [PushDeviceController::class, 'index']);
    Route::post('my/devices', [PushDeviceController::class, 'store']);
    Route::delete('my/devices/{device}', [PushDeviceController::class, 'destroy']);

    // Offre commerciale sans paiement en ligne (COM-03 à COM-07).
    Route::post('my/subscription-requests', [SubscriptionController::class, 'store'])->middleware('throttle:commercial');
    Route::delete('my/subscription-requests/{subscription}', [SubscriptionController::class, 'cancel']);
    Route::post('my/subscription-requests/{subscription}/payments', [SubscriptionController::class, 'declare'])->middleware('throttle:uploads');
    Route::get('my/subscription', [SubscriptionController::class, 'show']);
    Route::get('my/subscription/history', [SubscriptionController::class, 'history']);
    Route::post('my/subscription/downgrade', [SubscriptionController::class, 'downgrade']);
    Route::get('my/entitlements', [SubscriptionController::class, 'entitlements']);
    Route::get('my/premium', [PremiumController::class, 'index']);
    Route::delete('my/premium/{premium}', [PremiumController::class, 'cancel']);
    Route::post('my/premium/{premium}/payments', [PremiumController::class, 'declare'])->middleware('throttle:uploads');
    Route::post('my/listings/{listing}/premium', [PremiumController::class, 'store'])->middleware('throttle:commercial');

    // Annonces de l'annonceur connecté. `{listing}` se résout par ULID ; la Policy renvoie 404 pour l'annonce d'autrui.
    Route::prefix('my/listings')->group(function () {
        Route::get('/', [ListingController::class, 'index']);
        Route::post('/', [ListingController::class, 'store']);
        Route::get('{listing}', [ListingController::class, 'show']);
        Route::patch('{listing}', [ListingController::class, 'update']);
        Route::delete('{listing}', [ListingController::class, 'destroy']);
        Route::post('{listing}/submit', [ListingController::class, 'submit']);
        Route::post('{listing}/pause', [ListingController::class, 'pause']);
        Route::post('{listing}/resume', [ListingController::class, 'resume']);
        Route::post('{listing}/availability', [ListingAvailabilityController::class, 'store']);
        Route::post('{listing}/assign', [ListingController::class, 'assign']);
        Route::post('{listing}/close', [ListingController::class, 'close']);
        Route::post('{listing}/duplicate', [ListingController::class, 'duplicate']);
        Route::post('{listing}/images', [ListingImageController::class, 'store'])->middleware('throttle:uploads');
        Route::patch('{listing}/images/order', [ListingImageController::class, 'order']);
        Route::patch('{listing}/images/{image}/cover', [ListingImageController::class, 'cover']);
        Route::delete('{listing}/images/{image}', [ListingImageController::class, 'destroy']);
    });

    // Espace agence (AGC-01 à AGC-07). L'appartenance est relue à chaque requête : une suspension coupe l'accès immédiatement.
    Route::prefix('agency')->group(function () {
        Route::get('/', [AgencyController::class, 'show']);
        Route::patch('/', [AgencyController::class, 'update']);
        Route::post('logo', [AgencyController::class, 'logo'])->middleware('throttle:uploads');
        Route::get('stats', [AgencyController::class, 'stats']);
        Route::get('members', [AgencyTeamController::class, 'members']);
        Route::patch('members/{member}', [AgencyTeamController::class, 'updateMember']);
        Route::get('invitations', [AgencyTeamController::class, 'invitations']);
        Route::post('invitations', [AgencyTeamController::class, 'invite'])->middleware('throttle:invitations');
        Route::delete('invitations/{invitation}', [AgencyTeamController::class, 'revoke']);
        Route::post('invitations/accept', [AgencyTeamController::class, 'accept'])->middleware('throttle:invitation-accept');
        Route::get('verification', [AgencyVerificationController::class, 'show']);
        Route::post('verification', [AgencyVerificationController::class, 'store'])->middleware('throttle:uploads');
    });

    // Justificatifs de règlement : même principe (lien signé temporaire ET administrateur connecté).
    Route::get('admin/payment-proofs/{payment}', [CommercialController::class, 'downloadProof'])->middleware(['two-factor', 'signed'])->name('payment-proofs.download');

    // Justificatifs privés : lien signé temporaire ET administrateur connecté.
    Route::get('admin/verification-documents/{document}', [VerificationController::class, 'download'])
        ->middleware(['two-factor', 'signed'])->name('verification-documents.download');

    // Modération (ADM-01) : réservée aux rôles admin et super_admin.
    Route::prefix('admin')->middleware('two-factor')->group(function () {
        Route::get('moderation', [ModerationController::class, 'index']);
        Route::get('moderation/reasons', [ModerationController::class, 'reasons']);
        Route::get('dashboard', DashboardController::class);
        Route::get('audit-logs', [AuditLogController::class, 'index']);

        Route::get('reports', [ReportAdminController::class, 'index']);
        Route::post('reports/{report}/hide', [ReportAdminController::class, 'hide'])->whereNumber('report');
        Route::post('reports/{report}/warn', [ReportAdminController::class, 'warn'])->whereNumber('report');
        Route::post('reports/{report}/suspend-advertiser', [ReportAdminController::class, 'suspendAdvertiser'])->whereNumber('report');
        Route::post('reports/{report}/dismiss', [ReportAdminController::class, 'dismiss'])->whereNumber('report');

        Route::get('users', [UserAdminController::class, 'index']);
        Route::get('users/{user}', [UserAdminController::class, 'show']);
        Route::post('users/{user}/suspend', [UserAdminController::class, 'suspend']);
        Route::post('users/{user}/reactivate', [UserAdminController::class, 'reactivate']);
        Route::put('users/{user}/admin-role', [UserAdminController::class, 'adminRole']);
        Route::post('users/{user}/two-factor/reset', [UserAdminController::class, 'resetTwoFactor']);
        Route::get('agencies', [AgencyAdminController::class, 'index']);
        Route::get('agencies/{agency}', [AgencyAdminController::class, 'show']);
        Route::post('agencies/{agency}/suspend', [AgencyAdminController::class, 'suspend']);
        Route::post('agencies/{agency}/reactivate', [AgencyAdminController::class, 'reactivate']);
        Route::post('agencies/{agency}/revoke-verification', [AgencyAdminController::class, 'revokeVerification']);

        Route::prefix('referentials')->group(function () {
            Route::get('localities', [ReferentialController::class, 'localities']);
            Route::post('localities', [ReferentialController::class, 'storeLocality']);
            Route::patch('localities/{locality}', [ReferentialController::class, 'updateLocality'])->whereNumber('locality');
            Route::get('property-types', [ReferentialController::class, 'types']);
            Route::post('property-types', [ReferentialController::class, 'storeType']);
            Route::patch('property-types/{type}', [ReferentialController::class, 'updateType'])->whereNumber('type');
            Route::get('amenities', [ReferentialController::class, 'amenities']);
            Route::post('amenities', [ReferentialController::class, 'storeAmenity']);
            Route::patch('amenities/{amenity}', [ReferentialController::class, 'updateAmenity'])->whereNumber('amenity');
            Route::get('moderation-reasons', [ReferentialController::class, 'reasons']);
            Route::post('moderation-reasons', [ReferentialController::class, 'storeReason']);
            Route::patch('moderation-reasons/{reason}', [ReferentialController::class, 'updateReason'])->whereNumber('reason');
        });

        Route::get('notification-templates', [NotificationAdminController::class, 'templates']);
        Route::get('notification-templates/{type}/{channel}', [NotificationAdminController::class, 'versions']);
        Route::put('notification-templates/{type}/{channel}', [NotificationAdminController::class, 'updateTemplate']);
        Route::get('notification-deliveries', [NotificationAdminController::class, 'deliveries']);
        Route::post('notification-deliveries/{delivery}/retry', [NotificationAdminController::class, 'retry'])->whereNumber('delivery');
        Route::get('subscriptions', [CommercialController::class, 'subscriptions']);
        Route::get('subscriptions/{subscription}', [CommercialController::class, 'showSubscription']);
        Route::post('subscriptions/{subscription}/payments', [CommercialController::class, 'recordSubscriptionPayment'])->middleware('throttle:uploads');
        Route::post('subscriptions/{subscription}/activate', [CommercialController::class, 'activateSubscription']);
        Route::post('subscriptions/{subscription}/suspend', [CommercialController::class, 'suspendSubscription']);
        Route::post('subscriptions/{subscription}/reinstate', [CommercialController::class, 'reinstateSubscription']);
        Route::post('subscriptions/{subscription}/cancel', [CommercialController::class, 'cancelSubscription']);
        Route::get('premium', [CommercialController::class, 'premiumIndex']);
        Route::post('premium/{premium}/payments', [CommercialController::class, 'recordPremiumPayment'])->middleware('throttle:uploads');
        Route::post('premium/{premium}/activate', [CommercialController::class, 'activatePremium']);
        Route::post('premium/{premium}/suspend', [CommercialController::class, 'suspendPremium']);
        Route::post('premium/{premium}/cancel', [CommercialController::class, 'cancelPremium']);
        Route::post('payments/{payment}/confirm', [CommercialController::class, 'confirmPayment']);
        Route::post('payments/{payment}/reject', [CommercialController::class, 'rejectPayment']);
        Route::get('plans', [CommercialController::class, 'plans']);
        Route::post('plans', [CommercialController::class, 'storePlan']);
        Route::patch('plans/{plan}', [CommercialController::class, 'updatePlan']);
        Route::get('premium-products', [CommercialController::class, 'products']);
        Route::patch('premium-products/{product}', [CommercialController::class, 'updateProduct']);
        Route::get('settings/payment-instructions', [CommercialController::class, 'paymentInstructions']);
        Route::put('settings/payment-instructions', [CommercialController::class, 'updatePaymentInstructions']);
        Route::get('verifications', [VerificationController::class, 'index']);
        Route::get('verifications/{verification}', [VerificationController::class, 'show']);
        Route::post('verifications/{verification}/approve', [VerificationController::class, 'approve']);
        Route::post('verifications/{verification}/reject', [VerificationController::class, 'reject']);
        Route::post('listings/{listing}/approve', [ModerationController::class, 'approve']);
        Route::post('listings/{listing}/reject', [ModerationController::class, 'reject']);
    });
});
