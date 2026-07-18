<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * P0-02 acceptance: the test suite runs on real Postgres with PostGIS, citext and pg_trgm.
 * SQLite has none of these, so testing on it would test a different application.
 */
it('runs tests against PostgreSQL, not SQLite', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('has PostGIS available', function () {
    $version = DB::scalar('SELECT PostGIS_Version()');

    expect($version)->toBeString()->not->toBeEmpty();
});

it('has the citext and pg_trgm extensions installed', function () {
    $extensions = DB::table('pg_extension')->pluck('extname');

    expect($extensions)->toContain('postgis', 'citext', 'pg_trgm');
});
