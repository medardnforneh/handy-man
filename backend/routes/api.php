<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Reference\NoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — /api/v1
|--------------------------------------------------------------------------
|
| Additive-only, forever (CLAUDE.md rule #4). Never remove a field, never
| tighten a validation rule, never change an enum's meaning. New behaviour is
| a new field or a new endpoint. Old app builds live for months.
|
| The `api` group (registered in bootstrap/app.php) already applies the
| force-update gate. Version-specific routes live under the v1 prefix here.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    // Liveness/metadata — unauthenticated. Confirms which contract the server speaks.
    Route::get('/meta', function () {
        return response()->json([
            'api_version' => config('api.version'),
            'min_app_version' => config('api.min_app_version'),
            'server_time' => now()->toIso8601String(),
        ]);
    })->name('meta');

    // Auth — OTP-first (P1-02). Public: these ARE the authentication entry points. Token issuance
    // is added in P1-03.
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/otp/request', [OtpController::class, 'request'])->name('otp.request');
        Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
    });

    /*
    | REFERENCE vertical slice (P0-05) — the canonical example every feature copies.
    | Behind `auth` (the P1 work swaps this for `auth:sanctum`). Mutating routes automatically
    | require an Idempotency-Key via the global api middleware.
    */
    Route::middleware('auth')->group(function (): void {
        Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
        Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show');
    });

});
