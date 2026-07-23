<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\CashSettlementController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\DeliverableController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EscrowController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\LeadCreditController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\PaymentIntentController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProviderController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\Reference\NoteController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SafetyController;
use App\Http\Controllers\Api\V1\SiteVisitController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\VerificationDocumentController;
use App\Http\Controllers\Api\V1\WorkSessionController;
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

    // Published reviews for a party (P6-08) — public reputation signal.
    Route::get('/providers/{party}/reviews', [ReviewController::class, 'forParty'])->name('providers.reviews.index');

    // Payment gateway webhooks (P3-05) — public + server-to-server; authenticity is the signature,
    // not a token. Exempt from the Idempotency-Key requirement (see config/api.php exempt_paths).
    Route::post('/webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])->name('webhooks.payments');

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
        // Payment intents (P3-04) — start a MoMo collection (escrow or lead credits).
        Route::post('/payment-intents', [PaymentIntentController::class, 'store'])->name('payment-intents.store');
        // Provider's prepaid lead-credit balance (P3-07).
        Route::get('/provider/credits', [LeadCreditController::class, 'balance'])->name('provider.credits');
        // Provider payout request (P3-08).
        Route::post('/provider/payouts', [PayoutController::class, 'store'])->name('provider.payouts.store');

        // Escrow (P3-10/14). Customer approves a milestone (releases its slice) or refunds remaining.
        Route::post('/milestones/{milestone}/approve', [EscrowController::class, 'approveMilestone'])->name('milestones.approve');
        Route::post('/engagements/{engagement}/refund', [EscrowController::class, 'refund'])->name('engagements.refund');

        // Cash settlement recording (P3-15). The provider records a cash-settled amount; commission booked.
        Route::post('/engagements/{engagement}/cash-settlements', [CashSettlementController::class, 'store'])->name('engagements.cash-settlements.store');

        // Workspace conversation (P4-01/02). Participants read + post free-form; structured messages
        // are narrated by the server (a client posting a structured kind is rejected).
        Route::get('/jobs/{job}/messages', [MessageController::class, 'index'])->name('jobs.messages.index');
        Route::post('/jobs/{job}/messages', [MessageController::class, 'store'])->name('jobs.messages.store');

        // Deliverables (P4-08). Provider submits; customer accepts/rejects.
        Route::post('/engagements/{engagement}/deliverables', [DeliverableController::class, 'store'])->name('engagements.deliverables.store');
        Route::post('/deliverables/{deliverable}/review', [DeliverableController::class, 'review'])->name('deliverables.review');

        // Provider execution (P5-03 check-in/out geo + P5-06 status signals). The acting user must be
        // an active assigned worker; check-in exists only for onsite/hybrid engagements.
        Route::post('/engagements/{engagement}/check-in', [WorkSessionController::class, 'checkIn'])->name('engagements.check-in');
        Route::post('/engagements/{engagement}/check-out', [WorkSessionController::class, 'checkOut'])->name('engagements.check-out');
        Route::post('/engagements/{engagement}/status', [WorkSessionController::class, 'status'])->name('engagements.status');
        // On-site job report (P5-04). Multipart; before/after photos are EXIF-stripped server-side.
        Route::post('/engagements/{engagement}/report', [WorkSessionController::class, 'report'])->name('engagements.report');

        // Reviews (P6-08). Submit a double-blind review of the engagement's other party.
        Route::post('/engagements/{engagement}/reviews', [ReviewController::class, 'store'])->name('engagements.reviews.store');

        // Reports + blocks (P6-07). A block is honoured in search, ranking and offers.
        Route::get('/blocks', [SafetyController::class, 'blocks'])->name('blocks.index');
        Route::post('/blocks', [SafetyController::class, 'block'])->name('blocks.store');
        Route::delete('/blocks/{party}', [SafetyController::class, 'unblock'])->name('blocks.destroy');
        Route::post('/reports', [SafetyController::class, 'report'])->name('reports.store');

        // Verification documents (P6-01). Upload own identity/licence docs for admin review; the file
        // is encrypted at rest and only ever served via a signed short-TTL URL. Paths never returned.
        Route::get('/verification-documents', [VerificationDocumentController::class, 'index'])->name('verification-documents.index');
        Route::post('/verification-documents', [VerificationDocumentController::class, 'store'])->name('verification-documents.store');

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

        // Site visits (P2.5-04). A provider schedules a visit and completes it (fee creditable).
        Route::post('/jobs/{job}/site-visits', [SiteVisitController::class, 'store'])->name('jobs.site-visits.store');
        Route::post('/site-visits/{siteVisit}/complete', [SiteVisitController::class, 'complete'])->name('site-visits.complete');

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
