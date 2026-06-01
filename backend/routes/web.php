<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BackofficeController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

$frontendDist = realpath(base_path('../frontend/dist'));

$serveFrontendFile = function (string $filePath) {
    abort_unless(File::exists($filePath), 404);

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'js' => 'application/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'html' => 'text/html; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    return response(File::get($filePath), 200, [
        'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
        'Cache-Control' => $extension === 'html' ? 'no-cache' : 'public, max-age=31536000, immutable',
    ]);
};

Route::get('/assets/{path}', function (string $path) use ($frontendDist, $serveFrontendFile) {
    abort_unless($frontendDist, 404);

    return $serveFrontendFile($frontendDist.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.$path);
})->where('path', '.*');

Route::get('/favicon.svg', function () use ($frontendDist, $serveFrontendFile) {
    abort_unless($frontendDist, 404);

    return $serveFrontendFile($frontendDist.DIRECTORY_SEPARATOR.'favicon.svg');
});

Route::get('/icons.svg', function () use ($frontendDist, $serveFrontendFile) {
    abort_unless($frontendDist, 404);

    return $serveFrontendFile($frontendDist.DIRECTORY_SEPARATOR.'icons.svg');
});

Route::get('/media/{path}', function (string $path) {
    $baseDirectory = realpath(storage_path('app/public'));
    abort_unless($baseDirectory, 404);

    $resolvedPath = realpath($baseDirectory.DIRECTORY_SEPARATOR.$path);
    abort_unless($resolvedPath && str_starts_with($resolvedPath, $baseDirectory.DIRECTORY_SEPARATOR), 404);
    abort_unless(File::exists($resolvedPath), 404);

    $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    return response(File::get($resolvedPath), 200, [
        'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*');

Route::prefix('adminrepus1car')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
    });

    Route::middleware(['auth', 'role:admin'])->group(function (): void {
        Route::get('/', [BackofficeController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/teams', [BackofficeController::class, 'teams'])->name('admin.teams');
        Route::put('/teams/{team}/ranking', [BackofficeController::class, 'updateTeamRanking'])->name('admin.teams.ranking');
        Route::post('/teams/import-rankings', [BackofficeController::class, 'importTeamRankings'])->name('admin.teams.import-rankings');
        Route::get('/matches', [BackofficeController::class, 'matches'])->name('admin.matches');
        Route::post('/matches', [BackofficeController::class, 'storeMatch'])->name('admin.matches.store');
        Route::put('/matches/{match}', [BackofficeController::class, 'updateMatch'])->name('admin.matches.update');
        Route::post('/matches/{match}/finalize', [BackofficeController::class, 'finalizeMatch'])->name('admin.matches.finalize');
        Route::post('/matches/{match}/void', [BackofficeController::class, 'voidMatch'])->name('admin.matches.void');
        Route::post('/matches/approvals/{approval}/approve', [BackofficeController::class, 'approveMatchResult'])->name('admin.matches.approvals.approve');
        Route::post('/matches/approvals/{approval}/reject', [BackofficeController::class, 'rejectMatchResult'])->name('admin.matches.approvals.reject');
        Route::post('/matches/recalculate-all', [BackofficeController::class, 'recalculateAllScores'])->name('admin.matches.recalculate-all');
        Route::get('/rules', [BackofficeController::class, 'rules'])->name('admin.rules');
        Route::put('/rules/phases/{phase}', [BackofficeController::class, 'updatePhase'])->name('admin.rules.phase');
        Route::put('/rules/invoice', [BackofficeController::class, 'updateInvoiceSettings'])->name('admin.rules.invoice');
        Route::get('/prizes', [BackofficeController::class, 'prizes'])->name('admin.prizes');
        Route::post('/prizes', [BackofficeController::class, 'storePrize'])->name('admin.prizes.store');
        Route::put('/prizes/{prize}', [BackofficeController::class, 'updatePrize'])->name('admin.prizes.update');
        Route::delete('/prizes/{prize}', [BackofficeController::class, 'destroyPrize'])->name('admin.prizes.destroy');
        Route::get('/winners', [BackofficeController::class, 'winners'])->name('admin.winners');
        Route::get('/winners/acta', [BackofficeController::class, 'winnersActa'])->name('admin.winners.acta');
        Route::post('/winners/generate', [BackofficeController::class, 'generateWinners'])->name('admin.winners.generate');
        Route::post('/winners/resolve-draw', [BackofficeController::class, 'resolveDraw'])->name('admin.winners.resolve-draw');
        Route::get('/winners/{winner}/acta-comunicaciones', [BackofficeController::class, 'winnerCommunicationsActa'])->name('admin.winners.communications-acta');
        Route::post('/winners/{winner}/contact', [BackofficeController::class, 'logWinnerContact'])->name('admin.winners.contact');
        Route::post('/winners/{winner}/confirm', [BackofficeController::class, 'confirmWinner'])->name('admin.winners.confirm');
        Route::post('/winners/{winner}/deliver', [BackofficeController::class, 'deliverWinner'])->name('admin.winners.deliver');
        Route::post('/winners/{winner}/disqualify', [BackofficeController::class, 'disqualifyWinner'])->name('admin.winners.disqualify');
        Route::put('/winners/{winner}', [BackofficeController::class, 'updateWinner'])->name('admin.winners.update');
        Route::get('/integrations', [BackofficeController::class, 'integrations'])->name('admin.integrations');
        Route::put('/integrations/live-score', [BackofficeController::class, 'updateIntegrationSettings'])->name('admin.integrations.live-score');
        Route::post('/integrations/live-score/sync-fixtures', [BackofficeController::class, 'syncFixtures'])->name('admin.integrations.live-score.sync-fixtures');
        Route::post('/integrations/live-score/sync-live', [BackofficeController::class, 'syncLive'])->name('admin.integrations.live-score.sync-live');
        Route::post('/integrations/live-score/sync-commentary', [BackofficeController::class, 'syncCommentary'])->name('admin.integrations.live-score.sync-commentary');
        Route::get('/users', [BackofficeController::class, 'users'])->name('admin.users');
        Route::put('/users/{user}', [BackofficeController::class, 'updateUserStatus'])->name('admin.users.update');
        Route::get('/fraud', [BackofficeController::class, 'fraud'])->name('admin.fraud');
        Route::get('/fraud/export', [BackofficeController::class, 'exportFraudFlags'])->name('admin.fraud.export');
        Route::put('/fraud/{flag}', [BackofficeController::class, 'updateFraudFlag'])->name('admin.fraud.update');
        Route::get('/site', [BackofficeController::class, 'site'])->name('admin.site');
        Route::put('/site', [BackofficeController::class, 'updateSiteSettings'])->name('admin.site.update');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
    });
});

Route::get('/{any?}', function () use ($frontendDist, $serveFrontendFile) {
    abort_unless($frontendDist, 404, 'Frontend no compilado. Ejecuta npm run build en frontend.');

    return $serveFrontendFile($frontendDist.DIRECTORY_SEPARATOR.'index.html');
})->where('any', '^(?!api|up|admin|adminrepus1car).*$');
