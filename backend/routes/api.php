<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashierCouponController;
use App\Http\Controllers\Api\ClientTournamentController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DailyInvoiceGoalController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MatchCommentaryController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\PrizeController;
use App\Http\Controllers\Api\RedemptionController;
use App\Http\Controllers\Api\TeamFlagController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('google', [AuthController::class, 'google'])->middleware('throttle:auth-google');
    Route::post('forgot-password', [PasswordResetController::class, 'requestReset'])->middleware('throttle:password-reset');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('google/complete', [AuthController::class, 'completeGoogleRegistration'])->middleware('throttle:auth-register');
        Route::get('me', [AuthController::class, 'me']);
        Route::post('profile', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('newsletter')->group(function (): void {
    Route::post('subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:newsletter');
    Route::post('confirm', [NewsletterController::class, 'confirm'])->middleware('throttle:newsletter');
    Route::post('unsubscribe', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:newsletter');
});

Route::get('client/teams/{team}/flag', [TeamFlagController::class, 'show'])->name('api.client.teams.flag');

Route::middleware(['auth:sanctum', 'registration.complete'])->group(function (): void {
    Route::get('client/bootstrap', [ClientTournamentController::class, 'bootstrap']);
    Route::get('client/phases', [ClientTournamentController::class, 'phases']);
    Route::get('client/matches', [ClientTournamentController::class, 'matches']);
    Route::get('client/matches/{match}/commentary', [MatchCommentaryController::class, 'index']);
    Route::get('client/leaderboard', [ClientTournamentController::class, 'leaderboard']);
    Route::get('client/invoices', [DailyInvoiceGoalController::class, 'index'])->middleware('role:client');
    Route::get('client/predictions', [PredictionController::class, 'index'])->middleware('role:client');
    Route::post('client/matches/{match}/predict', [PredictionController::class, 'store'])->middleware('role:client');
    Route::post('client/invoices/resolve', [DailyInvoiceGoalController::class, 'resolve'])->middleware(['role:client', 'throttle:invoice-scan']);
    Route::post('client/invoices', [DailyInvoiceGoalController::class, 'store'])->middleware(['role:client', 'throttle:invoice-scan']);

    Route::get('dashboard', [DashboardController::class, 'show']);
    Route::get('wallet', [DashboardController::class, 'wallet']);

    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices/scan', [InvoiceController::class, 'store'])
        ->middleware(['role:client', 'throttle:invoice-scan']);

    Route::get('prizes/store', [PrizeController::class, 'index']);
    Route::post('redemptions', [RedemptionController::class, 'store'])
        ->middleware(['role:client', 'throttle:redeem-action']);
    Route::post('games/play', [GameController::class, 'store'])
        ->middleware(['role:client', 'throttle:game-action']);

    Route::get('coupons', [CouponController::class, 'index']);
    Route::get('coupons/{code}', [CouponController::class, 'show']);

    Route::get('push/subscriptions', [PushSubscriptionController::class, 'index'])->middleware('role:client');
    Route::post('push/subscriptions', [PushSubscriptionController::class, 'store'])->middleware('role:client');
    Route::delete('push/subscriptions', [PushSubscriptionController::class, 'destroy'])->middleware('role:client');

    Route::prefix('cashier')->middleware('role:cashier,admin')->group(function (): void {
        Route::post('coupons/scan', [CashierCouponController::class, 'scan'])->middleware('throttle:cashier-scan');
        Route::post('coupons/{code}/deliver', [CashierCouponController::class, 'deliver'])->middleware('throttle:cashier-scan');
    });

    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::get('summary', [AdminController::class, 'summary']);
        Route::get('campaigns', [AdminController::class, 'campaigns']);
        Route::post('campaigns', [AdminController::class, 'storeCampaign']);
        Route::put('campaigns/{campaign}', [AdminController::class, 'updateCampaign']);
        Route::get('prizes', [AdminController::class, 'prizes']);
        Route::post('prizes', [AdminController::class, 'storePrize']);
        Route::put('prizes/{prize}', [AdminController::class, 'updatePrize']);
        Route::get('windows', [AdminController::class, 'windows']);
        Route::post('windows', [AdminController::class, 'storeWindow']);
        Route::put('windows/{window}', [AdminController::class, 'updateWindow']);
        Route::get('audit-logs', [AdminController::class, 'auditLogs']);
    });
});

// Configuración pública (sin autenticación)
Route::get('public/settings', [PublicSettingsController::class, 'index']);
Route::get('public/branches', [PublicSettingsController::class, 'branches']);
Route::middleware(['auth:sanctum', 'role:admin'])->post('admin/settings/youtube', [PublicSettingsController::class, 'updateYoutubeId']);
