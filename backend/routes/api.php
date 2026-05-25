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
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\PrizeController;
use App\Http\Controllers\Api\RedemptionController;
use App\Http\Controllers\Api\TeamFlagController;
use App\Http\Controllers\Api\PublicSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('google', [AuthController::class, 'google']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('profile', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::get('client/teams/{team}/flag', [TeamFlagController::class, 'show'])->name('api.client.teams.flag');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('client/bootstrap', [ClientTournamentController::class, 'bootstrap']);
    Route::get('client/phases', [ClientTournamentController::class, 'phases']);
    Route::get('client/matches', [ClientTournamentController::class, 'matches']);
    Route::get('client/matches/{match}/commentary', [MatchCommentaryController::class, 'index']);
    Route::get('client/leaderboard', [ClientTournamentController::class, 'leaderboard']);
    Route::get('client/invoices', [DailyInvoiceGoalController::class, 'index'])->middleware('role:client');
    Route::get('client/predictions', [PredictionController::class, 'index'])->middleware('role:client');
    Route::post('client/matches/{match}/predict', [PredictionController::class, 'store'])->middleware('role:client');
    Route::post('client/invoices/resolve', [DailyInvoiceGoalController::class, 'resolve'])->middleware('role:client');
    Route::post('client/invoices', [DailyInvoiceGoalController::class, 'store'])->middleware('role:client');

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
Route::middleware('auth:sanctum')->post('admin/settings/youtube', [PublicSettingsController::class, 'updateYoutubeId']);
