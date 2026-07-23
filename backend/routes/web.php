<?php

declare(strict_types=1);

use App\Http\Controllers\VerificationDocumentViewController;
use Illuminate\Support\Facades\Route;

// Public SEO surface (Blade). Server-rendered so crawlers — and users without the app — can read
// it (doc 08). Locale is resolved per request by the SetLocale middleware (registered on the web
// group in bootstrap/app.php).
Route::get('/', fn () => view('public.home'))->name('home');

// Verification document access (P6-01, doc 04). The `signed` middleware enforces the 60s TTL — an
// expired or tampered URL is 403'd before the controller runs. Not in the /api group (a browser
// <img> can't send the app-version header the API gate requires).
Route::get('/verification-documents/{document}/view', VerificationDocumentViewController::class)
    ->middleware('signed')
    ->name('verification-documents.view');
