<?php

use App\Http\Controllers\Api\V1\Admin\ModerationController;
use App\Http\Controllers\Api\V1\Admin\VerificationController;
use App\Http\Controllers\Api\V1\AgencyController;
use App\Http\Controllers\Api\V1\AgencyTeamController;
use App\Http\Controllers\Api\V1\AgencyVerificationController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\DeviceController;
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
    Route::get('agencies/{slug}', [PublicAgencyController::class, 'show'])->where('slug', '[a-z0-9-]+');
    Route::get('passports/{reference}', [PassportController::class, 'show'])->where('reference', '[A-Za-z0-9-]+');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('listings/{listing}/reports', [ListingReportController::class, 'store'])->middleware('throttle:reports');

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

    // Justificatifs privés : lien signé temporaire ET administrateur connecté.
    Route::get('admin/verification-documents/{document}', [VerificationController::class, 'download'])
        ->middleware('signed')->name('verification-documents.download');

    // Modération (ADM-01) : réservée aux rôles admin et super_admin.
    Route::prefix('admin')->group(function () {
        Route::get('moderation', [ModerationController::class, 'index']);
        Route::get('moderation/reasons', [ModerationController::class, 'reasons']);
        Route::get('verifications', [VerificationController::class, 'index']);
        Route::get('verifications/{verification}', [VerificationController::class, 'show']);
        Route::post('verifications/{verification}/approve', [VerificationController::class, 'approve']);
        Route::post('verifications/{verification}/reject', [VerificationController::class, 'reject']);
        Route::post('listings/{listing}/approve', [ModerationController::class, 'approve']);
        Route::post('listings/{listing}/reject', [ModerationController::class, 'reject']);
    });
});
