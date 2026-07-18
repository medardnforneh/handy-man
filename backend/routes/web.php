<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Public SEO surface (Blade). Server-rendered so crawlers — and users without the app — can read
// it (doc 08). Locale is resolved per request by the SetLocale middleware (registered on the web
// group in bootstrap/app.php).
Route::get('/', fn () => view('public.home'))->name('home');
