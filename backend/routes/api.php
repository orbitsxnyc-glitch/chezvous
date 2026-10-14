<?php

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MeController;
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
