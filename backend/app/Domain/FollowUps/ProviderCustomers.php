<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Models\DoNotContact;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The provider CRM customer list (build plan P7-08, doc 07). For each customer the provider has
 * engaged: job count, completions, lifetime value, last engagement — the client book that makes a
 * provider's reputation feel like an asset held on the platform. Do-not-contact is surfaced per row.
 */
final class ProviderCustomers
{
    /**
     * @return list<array{customer_party_id: string, customer_name: string, job_count: int, completed_count: int, lifetime_value_minor: int, last_engaged_at: string|null, do_not_contact: bool}>
     */
    public function forProvider(string $providerPartyId): array
    {
        $rows = DB::table('engagements as e')
            ->join('service_jobs as j', 'j.id', '=', 'e.job_id')
            ->join('parties as p', 'p.id', '=', 'j.customer_party_id')
            ->where('e.provider_party_id', $providerPartyId)
            ->groupBy('j.customer_party_id', 'p.display_name')
            ->selectRaw('j.customer_party_id as customer_party_id, p.display_name as customer_name,
                count(*) as job_count,
                count(*) filter (where e.completed_at is not null) as completed_count,
                coalesce(sum(e.agreed_amount_minor), 0) as lifetime_value_minor,
                max(e.accepted_at) as last_engaged_at')
            ->orderByDesc('last_engaged_at')
            ->get();

        $blocked = DoNotContact::query()
            ->where('provider_party_id', $providerPartyId)
            ->pluck('customer_party_id')
            ->flip();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'customer_party_id' => (string) $r->customer_party_id,
                'customer_name' => (string) $r->customer_name,
                'job_count' => (int) $r->job_count,
                'completed_count' => (int) $r->completed_count,
                'lifetime_value_minor' => (int) $r->lifetime_value_minor,
                // ISO-8601 with offset, per the API convention. This is an aggregate over a raw
                // query, so nothing casts it for us and Postgres hands back its own
                // "2026-07-28 05:15:57+00" — which V8 happens to parse and stricter engines
                // (iOS JSC, older WebViews) reject outright as an invalid date.
                'last_engaged_at' => $r->last_engaged_at !== null
                    ? CarbonImmutable::parse((string) $r->last_engaged_at)->toIso8601String()
                    : null,
                'do_not_contact' => $blocked->has($r->customer_party_id),
            ];
        }

        return $out;
    }
}
