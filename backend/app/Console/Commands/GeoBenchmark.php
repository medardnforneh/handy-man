<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServiceArea;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The P1-06 acceptance criterion, made runnable: an `ST_DWithin` proximity query must stay under
 * 50ms against 100k addresses.
 *
 * It lives in a command rather than the test suite on purpose. Seeding 100k geography rows takes
 * long enough that putting it in `composer test` would tax every run for a property that changes
 * only when the index or the query does. This is the thing you run when you touch either.
 *
 * Points are scattered over the real Yaoundé and Douala bounding boxes rather than uniformly over
 * the globe: an index benchmarked against uniformly random points is not benchmarked (doc 05's
 * testing floor says the same about dispatch ranking). Uniform noise makes every query selective,
 * which is precisely the case a GIST index finds easy.
 */
final class GeoBenchmark extends Command
{
    protected $signature = 'perf:geo-benchmark
        {--target=addresses : addresses (P1-06) or areas (provider-search coverage)}
        {--rows=100000 : How many addresses to seed}
        {--radius=5000 : Search radius in metres}
        {--runs=20 : How many timed queries to average}
        {--budget=50 : The pass/fail budget in milliseconds}
        {--keep : Leave the seeded rows behind}';

    protected $description = 'P1-06: prove ST_DWithin stays under budget on a 100k-address table';

    public function handle(): int
    {
        $rows = (int) $this->option('rows');
        $radius = (float) $this->option('radius');
        $runs = (int) $this->option('runs');
        $budget = (float) $this->option('budget');

        $areas = $this->option('target') === 'areas';
        $table = $areas ? 'service_areas' : 'addresses';
        // The coverage query's distance comes from a COLUMN (each provider's own radius), which a
        // plain GIST index cannot bound the way a constant radius can. That is the whole reason this
        // target exists: it is the provider-search hot path and the one likeliest to scale badly.
        $predicate = $areas
            // Mirrors ServiceArea::scopeCovering exactly — an index-served constant bound, then the
            // exact per-row radius. Benchmarking a different query than production runs is theatre.
            ? 'ST_DWithin(center, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, '.ServiceArea::MAX_RADIUS_M.')
               AND ST_DWithin(center, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, radius_m)'
            : 'ST_DWithin(point, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)';

        $this->components->info("Seeding {$rows} rows into {$table} across Yaoundé and Douala…");
        $party = $this->anchorParty();
        $areas ? $this->seedAreas($rows, $party) : $this->seed($rows, $party);

        // Without ANALYZE the planner works from stale statistics and may ignore the index — which
        // would benchmark the wrong thing, and flatteringly or not depending on the day.
        DB::statement("ANALYZE {$table}");

        $plan = $this->plan($predicate, $table, $radius, $areas);
        $usesIndex = str_contains($plan, 'Index Scan') || str_contains($plan, 'Bitmap Index Scan');
        $this->line('');
        $this->components->twoColumnDetail('Target', $table);
        $this->components->twoColumnDetail('Rows in table', (string) DB::table($table)->count());
        $this->components->twoColumnDetail('Index used', $usesIndex ? '<fg=green>yes</>' : '<fg=red>NO — sequential scan</>');

        $timings = [];
        for ($i = 0; $i < $runs; $i++) {
            [$lat, $lng] = $this->randomPoint();
            $start = hrtime(true);
            DB::select(
                "SELECT id FROM {$table} WHERE {$predicate} LIMIT 50",
                $areas ? [$lng, $lat, $lng, $lat] : [$lng, $lat, $radius],
            );
            $timings[] = (hrtime(true) - $start) / 1_000_000; // ns → ms
        }

        // The worst case for a scan-based plan is a point that matches NOTHING: `LIMIT` cannot exit
        // early, so every row is examined. Dense hits flatter a sequential scan badly, and a rural
        // job or a thin corner of the map is exactly where that flattery stops.
        $farStart = hrtime(true);
        DB::select(
            "SELECT id FROM {$table} WHERE {$predicate} LIMIT 50",
            $areas ? [0.0, 0.0, 0.0, 0.0] : [0.0, 0.0, $radius],
        );
        $noMatchMs = (hrtime(true) - $farStart) / 1_000_000;

        sort($timings);
        $median = $timings[intdiv($runs, 2)];
        $p95 = $timings[max((int) floor($runs * 0.95) - 1, 0)];
        $worst = end($timings);

        $this->components->twoColumnDetail('Median', sprintf('%.2f ms', $median));
        $this->components->twoColumnDetail('p95', sprintf('%.2f ms', $p95));
        $this->components->twoColumnDetail('Worst', sprintf('%.2f ms', $worst));
        $this->components->twoColumnDetail('No-match point (no early exit)', sprintf('%.2f ms', $noMatchMs));
        $this->line('');

        if (! $this->option('keep')) {
            $areas
                ? DB::statement('DELETE FROM service_areas WHERE provider_profile_id IN (SELECT id FROM provider_profiles WHERE party_id = ?)', [$party])
                : DB::table('addresses')->where('party_id', $party)->delete();
            DB::table('provider_profiles')->where('party_id', $party)->delete();
            DB::table('parties')->where('id', $party)->delete();
        }

        // p95 rather than the median: the criterion is about what users experience, and a median
        // that passes while one query in twenty takes half a second is not a passing search.
        if (! $usesIndex) {
            $this->components->error('ST_DWithin is NOT index-served — the GIST index is not doing its job.');

            return self::FAILURE;
        }
        if ($p95 > $budget) {
            $this->components->error(sprintf('p95 %.2f ms exceeds the %.0f ms budget (P1-06).', $p95, $budget));

            return self::FAILURE;
        }

        $this->components->info(sprintf('P1-06 met: p95 %.2f ms under the %.0f ms budget on %d rows.', $p95, $budget, $rows));

        return self::SUCCESS;
    }

    /** One throwaway party to own the seeded rows, so cleanup is a single delete. */
    private function anchorParty(): string
    {
        $id = (string) Str::uuid();
        DB::table('parties')->insert([
            'id' => $id, 'kind' => 'individual', 'display_name' => 'perf-benchmark', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function seed(int $rows, string $party): void
    {
        // Bulk-insert straight through SQL: 100k Eloquent models would measure the ORM, not the index.
        $chunk = 5000;
        $bar = $this->output->createProgressBar((int) ceil($rows / $chunk));
        for ($done = 0; $done < $rows; $done += $chunk) {
            $batch = min($chunk, $rows - $done);
            DB::statement(
                'INSERT INTO addresses (id, party_id, line1, city, country_code, point, created_at, updated_at)
                 SELECT gen_random_uuid(), ?, \'perf\', ?, \'CM\',
                        ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography, now(), now()
                 FROM (
                     SELECT CASE WHEN g % 2 = 0 THEN 11.45 + random() * 0.25 ELSE 9.65 + random() * 0.25 END AS lng,
                            CASE WHEN g % 2 = 0 THEN 3.78 + random() * 0.25 ELSE 4.00 + random() * 0.25 END AS lat,
                            g
                     FROM generate_series(1, ?) AS g
                 ) pts',
                [$party, 'Yaoundé', $batch],
            );
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
    }

    private function plan(string $predicate, string $table, float $radius, bool $areas): string
    {
        [$lat, $lng] = $this->randomPoint();
        $rows = DB::select(
            "EXPLAIN SELECT id FROM {$table} WHERE {$predicate} LIMIT 50",
            $areas ? [$lng, $lat, $lng, $lat] : [$lng, $lat, $radius],
        );

        return implode("\n", array_map(fn ($r) => (string) reset($r), $rows));
    }

    /**
     * Seed provider profiles with one service area each — the shape provider search queries.
     * Radii spread across the legal 500m–100km range, because a table where every disc is the same
     * size would not exercise the variable-distance problem this target exists to measure.
     */
    private function seedAreas(int $rows, string $party): void
    {
        $profile = (string) Str::uuid();
        DB::table('provider_profiles')->insert([
            'id' => $profile, 'party_id' => $party, 'headline' => 'perf', 'verification_tier' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $chunk = 5000;
        $bar = $this->output->createProgressBar((int) ceil($rows / $chunk));
        for ($done = 0; $done < $rows; $done += $chunk) {
            $batch = min($chunk, $rows - $done);
            DB::statement(
                'INSERT INTO service_areas (id, provider_profile_id, center, radius_m, created_at)
                 SELECT gen_random_uuid(), ?, ST_SetSRID(ST_MakePoint(lng, lat), 4326)::geography,
                        (2000 + floor(random() * 28000))::int, now()
                 FROM (
                     SELECT CASE WHEN g % 2 = 0 THEN 11.45 + random() * 0.25 ELSE 9.65 + random() * 0.25 END AS lng,
                            CASE WHEN g % 2 = 0 THEN 3.78 + random() * 0.25 ELSE 4.00 + random() * 0.25 END AS lat,
                            g
                     FROM generate_series(1, ?) AS g
                 ) pts',
                [$profile, $batch],
            );
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
    }

    /** @return array{0: float, 1: float} */
    private function randomPoint(): array
    {
        return random_int(0, 1) === 0
            ? [3.78 + (random_int(0, 250) / 1000), 11.45 + (random_int(0, 250) / 1000)]  // Yaoundé
            : [4.00 + (random_int(0, 250) / 1000), 9.65 + (random_int(0, 250) / 1000)];  // Douala
    }
}
