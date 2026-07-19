<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\Reference\NoteController;
use App\Http\Controllers\Api\V1\SkillController;
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

    // Skills catalog (P1-07) — public discovery (no app bundle needed, doc 08).
    Route::get('/skills', [SkillController::class, 'index'])->name('skills.index');
    Route::get('/skills/search', [SkillController::class, 'search'])->name('skills.search');

    // Auth — OTP-first (P1-02). Public: these ARE the authentication entry points. Token issuance
    // is added in P1-03.
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/otp/request', [OtpController::class, 'request'])->name('otp.request');
        Route::post('/otp/verify', [OtpController::class, 'verify'])->name('otp.verify');
        // The refresh token is itself the credential (P1-03) — no bearer required.
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    /*
    | REFERENCE vertical slice (P0-05) — the canonical example every feature copies.
    | Behind `auth:sanctum` (token auth). Mutating routes automatically require an Idempotency-Key
    | via the global api middleware.
    */
    Route::middleware('auth:sanctum')->group(function (): void {
        // Device registration / push token capture (P1-04).
        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');

        // Consents (P1-05) and language preferences (P1-05b).
        Route::get('/consents', [ConsentController::class, 'index'])->name('consents.index');
        Route::post('/consents', [ConsentController::class, 'store'])->name('consents.store');
        Route::patch('/me/preferences', [ProfileController::class, 'updatePreferences'])->name('me.preferences');
        // DSAR export + right to erasure (P1-10, Law 2024/017).
        Route::get('/me/data-export', [ProfileController::class, 'dataExport'])->name('me.data-export');
        Route::delete('/me', [ProfileController::class, 'erase'])->name('me.erase');

        // Addresses (P1-06) — creating one requires location_tracking consent.
        Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
        Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');

        // Jobs (P2-03). Responses are PII-minimised — a pre-engagement provider never sees the
        // customer's exact address.
        Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
        Route::post('/jobs', [JobController::class, 'store'])->name('jobs.store');
        Route::get('/jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
        Route::post('/jobs/{job}/publish', [JobController::class, 'publish'])->name('jobs.publish');
        // Matching providers (P2-04) — geo-filtered for onsite/hybrid, whole pool for remote.
        Route::get('/jobs/{job}/providers', [JobController::class, 'providers'])->name('jobs.providers');
        // Direct offers (P2-05).
        Route::post('/jobs/{job}/offers', [OfferController::class, 'store'])->name('jobs.offers.store');
        // Provider accepts an offer → engagement (P2-06). Concurrency-safe; fact-gated (P2-06b).
        Route::post('/offers/{offer}/accept', [OfferController::class, 'accept'])->name('offers.accept');

        // Quotations (P2.5-01). A provider submits a priced quote; revision is a new version.
        Route::post('/jobs/{job}/quotations', [QuotationController::class, 'store'])->name('jobs.quotations.store');
        Route::post('/quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise');
        // Customer accepts a quotation → engagement + milestones (P2.5-05).
        Route::post('/quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');

        // Engagement staffing (P2-08). Dispatcher-only (org-internal RBAC via EngagementPolicy); the
        // worker must belong to the provider (app check + DB trigger).
        Route::post('/engagements/{engagement}/assignments', [AssignmentController::class, 'store'])->name('engagements.assignments.store');
        Route::delete('/engagements/{engagement}/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('engagements.assignments.destroy');

        // Provider section (P1-08). Profile creation is always allowed (doc 10); listing a skill is
        // fact-gated on having a profile; a service area requires location_tracking consent.
        Route::get('/provider/profile', [ProviderController::class, 'showProfile'])->name('provider.profile.show');
        Route::post('/provider/profile', [ProviderController::class, 'storeProfile'])->name('provider.profile.store');
        Route::post('/provider/skills', [ProviderController::class, 'storeSkill'])->name('provider.skills.store');
        Route::post('/provider/service-areas', [ProviderController::class, 'storeServiceArea'])->name('provider.service-areas.store');

        // Reference vertical slice (P0-05).
        Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
        Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show');
    });

});
