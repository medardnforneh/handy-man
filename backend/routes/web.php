<?php

declare(strict_types=1);

use App\Http\Controllers\EngagementShareViewController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VerificationDocumentViewController;
use Illuminate\Support\Facades\Route;

// Public SEO surface (Blade). Server-rendered so crawlers — and users without the app — can read
// it (doc 08). Locale is resolved per request by the SetLocale middleware (registered on the web
// group in bootstrap/app.php).
Route::get('/', PublicHomeController::class)->name('home');

// The crawlable services directory — the taxonomy (P1-07) is the site's SEO surface. Every trade
// is a real search term in both languages, and a leaf page lists who offers it (PII-minimised:
// headline + reputation only, exactly as the API's pre-engagement match list does).
Route::get('/services', [PublicServiceController::class, 'index'])->name('services.index');
Route::get('/services/{slug}', [PublicServiceController::class, 'show'])->name('services.show');

// Discovery for crawlers. robots.txt disallows the grant URLs (signed documents, share tokens).
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Verification document access (P6-01, doc 04). The `signed` middleware enforces the 60s TTL — an
// expired or tampered URL is 403'd before the controller runs. Not in the /api group (a browser
// <img> can't send the app-version header the API gate requires).
Route::get('/verification-documents/{document}/view', VerificationDocumentViewController::class)
    ->middleware('signed')
    ->name('verification-documents.view');

// Share-my-job public status page (P6-05, doc 04). The opaque token is the grant; it expires and is
// revocable. A stale/revoked token is a 404. Read-only, PII-minimised.
Route::get('/s/{token}', EngagementShareViewController::class)->name('engagement.share');
