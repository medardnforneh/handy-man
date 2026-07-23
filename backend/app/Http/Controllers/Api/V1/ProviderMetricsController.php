<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Metrics\ProviderMetrics;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public provider performance metrics (build plan P6-12). Only display-safe metrics are returned:
 * a rate computed from below the sample floor comes back null (never "100% on-time (1 job)"). The
 * repeat-customer rate and leakage flag are NOT exposed here — they are an internal admin signal.
 */
final class ProviderMetricsController extends Controller
{
    public function forParty(string $party, ProviderMetrics $metrics): JsonResponse
    {
        $m = $metrics->forParty($party);

        return response()->json([
            'data' => [
                'jobs_completed_90d' => $m['jobs_completed_90d'],
                'rating_avg' => $m['rating_avg'],
                'rating_count' => $m['rating_count'],
                'on_time_rate' => $m['on_time_rate'],
                'on_time_sample' => $m['on_time_sample'],
            ],
        ]);
    }
}
