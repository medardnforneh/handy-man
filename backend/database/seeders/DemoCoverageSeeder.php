<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Safety\SafetyAlertKind;
use App\Models\Assignment;
use App\Models\Block;
use App\Models\CashSettlement;
use App\Models\Consent;
use App\Models\Deliverable;
use App\Models\Device;
use App\Models\Dispute;
use App\Models\DoNotContact;
use App\Models\EmergencyContact;
use App\Models\Engagement;
use App\Models\EngagementShare;
use App\Models\FollowUp;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\JobReport;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Report;
use App\Models\Review;
use App\Models\SafetyAlert;
use App\Models\ServiceArea;
use App\Models\SiteVisit;
use App\Models\Skill;
use App\Models\User;
use App\Models\VerificationDocument;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Models\WorkSession;
use App\Support\ActivityLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Coverage for everything DemoSeeder's money story does not touch.
 *
 * DemoSeeder builds a believable marketplace: jobs, quotes, escrow, releases, a payout. That leaves
 * most of Phase 6, 7 and 8 with EMPTY admin queues and empty app screens — which is how a panel ends
 * up looking finished while half of it has never been seen with a row in it. Two of this session's
 * bugs (an audit table that crashed on its first real row, an actor column that said "System") were
 * invisible precisely because nothing had ever populated those tables.
 *
 * So this seeder exists to make every surface non-empty: verification queue, reports, disputes,
 * safety alerts, referrals, reviews, warranties, follow-ups, site visits, deliverables, work
 * sessions, job reports, cash settlements, offers, consents, devices and the audit log.
 *
 * Idempotent (firstOrCreate / existence checks), so re-running it does not multiply the data.
 */
final class DemoCoverageSeeder extends Seeder
{
    public function run(): void
    {
        /** @var Collection<int, User> $providers */
        $providers = User::query()->whereHas('party.providerProfile')->orderBy('phone_e164')->get();
        /** @var Collection<int, User> $customers */
        $customers = User::query()->whereDoesntHave('party.providerProfile')->orderBy('phone_e164')->get();
        $admin = User::query()->where('email', 'admin@handyman.cm')->first();

        if ($providers->count() < 3 || $customers->count() < 3 || $admin === null) {
            $this->command->warn('DemoCoverageSeeder: run DemoSeeder first.');

            return;
        }

        $this->supply();
        $this->identity($providers, $customers, $admin);
        $this->trustAndSafety($providers, $customers, $admin);
        $this->reputation($providers, $customers);
        $this->growth($providers, $customers);
        $this->execution($providers, $customers);
        $this->lifecycle($providers, $customers);

        $this->command->info('Coverage data seeded — every admin queue and app screen now has rows.');
    }

    /** Consents, devices, emergency contacts and the verification queue. */
    /**
     * The supply side: which trades each provider actually offers, and where they will travel.
     *
     * Without these two tables the marketplace has no inventory at all — every public trade page
     * reads "no one offers this", and ProviderSearch (skill match + service-area coverage) returns
     * nobody for any on-site job, so the customer's core "find someone" flow is empty in the demo.
     * The provider profiles alone were never enough.
     */
    private function supply(): void
    {
        if (ProviderSkill::query()->exists()) {
            return;
        }

        $leaves = Skill::query()->where('is_leaf', true)->orderBy('name_en')->get();
        $profiles = ProviderProfile::query()->orderBy('created_at')->get();
        if ($leaves->isEmpty() || $profiles->isEmpty()) {
            return;
        }

        // Real coordinates, because ranking and radius tested against uniformly random points is not
        // tested (doc 05's testing floor). Yaoundé and Douala, alternating.
        $cities = [
            ['lat' => 3.8480, 'lng' => 11.5021],  // Yaoundé
            ['lat' => 4.0511, 'lng' => 9.7679],   // Douala
        ];
        $models = ['hourly', 'fixed', 'quote_only'];

        // Iterate the TRADES, not the providers: every leaf gets two providers, so no directory page
        // in the taxonomy is a dead end and every trade has enough supply for ranking to mean
        // something. Walking providers instead covered only the first stretch of the taxonomy and
        // left most trade pages reading "no one offers this".
        foreach ($leaves as $l => $skill) {
            for ($n = 0; $n < 2; $n++) {
                $profile = $profiles[($l * 2 + $n) % $profiles->count()];
                ProviderSkill::query()->firstOrCreate(
                    ['provider_profile_id' => $profile->id, 'skill_id' => $skill->id],
                    [
                        'price_model' => $models[($l + $n) % 3],
                        'rate_minor' => [5_000, 25_000, null][($l + $n) % 3],
                        'currency' => 'XAF',
                        'years_experience' => 2 + (($l + $n) % 8),
                    ],
                );
            }
        }

        foreach ($profiles as $i => $profile) {
            $city = $cities[$i % 2];
            ServiceArea::query()->firstOrCreate(
                ['provider_profile_id' => $profile->id],
                [
                    'center' => new Point($city['lat'], $city['lng']),
                    'radius_m' => 8_000 + ($i % 4) * 4_000,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, User>  $providers
     * @param  Collection<int, User>  $customers
     */
    private function identity(Collection $providers, Collection $customers, User $admin): void
    {
        foreach ($customers->take(4) as $customer) {
            foreach (['terms', 'privacy', 'location_tracking', 'marketing'] as $purpose) {
                Consent::query()->firstOrCreate(
                    ['user_id' => $customer->id, 'purpose' => $purpose],
                    [
                        // One customer has revoked marketing, so the consent gate in the follow-up
                        // engine has something real to refuse.
                        'granted' => ! ($purpose === 'marketing' && $customer->is($customers->first())),
                        'policy_version' => '2026-01',
                        'presented_locale' => 'fr',
                        'created_at' => now()->subDays(30),
                    ],
                );
            }

            // `devices.id` IS the client-supplied X-Device-Id and is a real uuid column, so it
            // cannot be a readable slug. Key the idempotency on the owner instead.
            Device::query()->firstOrCreate(
                ['user_id' => $customer->id, 'platform' => 'android'],
                [
                    'id' => (string) Str::uuid(),
                    'push_token' => 'demo-token-'.substr($customer->id, 0, 12),
                    'app_version' => '1.0.0',
                ],
            );
        }

        foreach ($providers->take(3) as $i => $provider) {
            EmergencyContact::query()->firstOrCreate(
                ['user_id' => $provider->id, 'phone_e164' => '+23769900'.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT)],
                ['name' => ['Marceline Kamga', 'Thomas Nkeng', 'Berthe Fotso'][$i]],
            );
        }

        // A verification queue with something in every state: one waiting for review (the queue's
        // reason to exist), one approved, one rejected.
        $states = [
            ['status' => 'pending', 'kind' => 'national_id_front'],
            ['status' => 'approved', 'kind' => 'selfie'],
            ['status' => 'rejected', 'kind' => 'rccm'],
        ];
        foreach ($providers->take(3) as $i => $provider) {
            $state = $states[$i];
            $existing = VerificationDocument::query()
                ->where('party_id', $provider->party_id)
                ->where('kind', $state['kind'])
                ->exists();
            if ($existing) {
                continue;
            }

            VerificationDocument::factory()->create([
                'party_id' => $provider->party_id,
                'kind' => $state['kind'],
                'status' => $state['status'],
                'reviewed_by_user_id' => $state['status'] === 'pending' ? null : $admin->id,
                'reviewed_at' => $state['status'] === 'pending' ? null : now()->subDays(3),
                'reject_reason' => $state['status'] === 'rejected' ? 'Document illisible — merci de renvoyer une photo nette.' : null,
            ]);
        }

        // An audit row for a document view: the insider-threat control (P6-02) with evidence in it.
        app(ActivityLogger::class)->log(
            'verification_document.viewed',
            VerificationDocument::query()->first(),
            $admin->id,
            ['reason' => 'Routine review'],
            '127.0.0.1',
        );
    }

    /** Reports, blocks, disputes and safety alerts. */
    /**
     * @param  Collection<int, User>  $providers
     * @param  Collection<int, User>  $customers
     */
    private function trustAndSafety(Collection $providers, Collection $customers, User $admin): void
    {
        Report::query()->firstOrCreate(
            [
                'reporter_party_id' => $customers[1]->party_id,
                'subject_party_id' => $providers[1]->party_id,
                'category' => 'off_platform',
            ],
            [
                'body' => 'Après la visite, le technicien a proposé de faire le reste des travaux directement, '
                    .'sans passer par la plateforme, et m’a donné son numéro personnel pour discuter du prix.',
                'status' => 'open',
                'created_at' => now()->subDays(2),
            ],
        );

        Report::query()->firstOrCreate(
            [
                'reporter_party_id' => $customers[2]->party_id,
                'subject_party_id' => $providers[2]->party_id,
                'category' => 'no_show',
            ],
            [
                'body' => 'Rendez-vous fixé à 9h, personne n’est venu et je n’ai pas eu de nouvelles de la journée.',
                'status' => 'resolved',
                'resolved_at' => now()->subDay(),
                'created_at' => now()->subDays(6),
            ],
        );

        // A block in each direction is not needed — the model honours it bidirectionally, and one
        // pair is enough to prove search and offers exclude it.
        Block::query()->firstOrCreate([
            'party_id' => $customers[2]->party_id,
            'blocked_party_id' => $providers[2]->party_id,
        ]);

        $engagements = Engagement::query()->with('job')->latest('accepted_at')->take(3)->get();
        if ($engagements->isNotEmpty()) {
            Dispute::query()->firstOrCreate(
                ['engagement_id' => $engagements[0]->id, 'raised_by_party_id' => $engagements[0]->job->customer_party_id],
                [
                    'category' => 'quality',
                    'body' => 'Le technicien est venu deux fois mais la climatisation ne refroidit toujours pas. '
                        .'Il dit que le compresseur est en cause, alors que le devis couvrait la recharge de gaz '
                        .'et le nettoyage complet. Je demande soit une nouvelle intervention sans frais, soit le '
                        .'remboursement de la seconde tranche.',
                    'status' => 'open',
                    'created_at' => now()->subDays(3),
                ],
            );

            if ($engagements->count() > 1) {
                Dispute::query()->firstOrCreate(
                    ['engagement_id' => $engagements[1]->id, 'raised_by_party_id' => $engagements[1]->job->customer_party_id],
                    [
                        'category' => 'scope',
                        'body' => 'Le devis mentionnait la peinture de deux pièces, seule une a été faite.',
                        'status' => 'resolved',
                        'resolution_note' => 'Les deux parties ont convenu d’un rattrapage la semaine suivante.',
                        'resolved_by_user_id' => $admin->id,
                        'resolved_at' => now()->subDay(),
                        'created_at' => now()->subDays(9),
                    ],
                );
            }
        }

        if (! SafetyAlert::query()->where('kind', SafetyAlertKind::Panic->value)->exists()) {
            SafetyAlert::factory()->create([
                'user_id' => $providers[0]->id,
                'kind' => SafetyAlertKind::Panic->value,
                'status' => 'open',
                'note' => 'Le client est devenu agressif quand j’ai expliqué le supplément.',
                'created_at' => now()->subMinutes(37),
            ]);
        }

        if (! SafetyAlert::query()->where('kind', SafetyAlertKind::CheckInOverdue->value)->exists()) {
            SafetyAlert::factory()->create([
                'user_id' => $providers[1]->id,
                'kind' => SafetyAlertKind::CheckInOverdue->value,
                'status' => 'resolved',
                'note' => null,
                'created_at' => now()->subDays(2),
                'resolved_at' => now()->subDays(2)->addHours(1),
                'resolved_by_user_id' => $admin->id,
            ]);
        }
    }

    /** Reviews (both revealed and pending) and warranties. */
    /**
     * @param  Collection<int, User>  $providers
     * @param  Collection<int, User>  $customers
     */
    private function reputation(Collection $providers, Collection $customers): void
    {
        $completed = Engagement::query()->with('job')->whereNotNull('completed_at')->take(2)->get();

        foreach ($completed as $i => $engagement) {
            $customerParty = $engagement->job->customer_party_id;
            $providerParty = $engagement->provider_party_id;

            // The first pair is fully revealed (both sides submitted); the second is left waiting on
            // the provider, which is the state the double-blind window actually spends most time in.
            Review::query()->firstOrCreate(
                ['engagement_id' => $engagement->id, 'author_party_id' => $customerParty],
                [
                    'subject_party_id' => $providerParty,
                    'rating' => $i === 0 ? 5 : 4,
                    'body' => $i === 0
                        ? 'Travail impeccable, ponctuel et très propre. Je recommande.'
                        : 'Bon travail dans l’ensemble, un peu de retard le premier jour.',
                    'visibility' => $i === 0 ? 'published' : 'pending',
                    'window_closes_at' => now()->addDays(10),
                    'published_at' => $i === 0 ? now()->subDays(2) : null,
                ],
            );

            if ($i === 0) {
                Review::query()->firstOrCreate(
                    ['engagement_id' => $engagement->id, 'author_party_id' => $providerParty],
                    [
                        'subject_party_id' => $customerParty,
                        'rating' => 5,
                        'body' => 'Client clair sur ses attentes et paiement rapide.',
                        'visibility' => 'published',
                        'window_closes_at' => now()->addDays(10),
                        'published_at' => now()->subDays(2),
                    ],
                );
            }
        }

        if ($completed->isNotEmpty() && ! Warranty::query()->exists()) {
            $warranty = Warranty::factory()->create([
                'engagement_id' => $completed[0]->id,
                'duration_days' => 90,
                'starts_at' => now()->subDays(20),
                'expires_at' => now()->addDays(70),
                'status' => 'active',
            ]);

            WarrantyClaim::factory()->create([
                'warranty_id' => $warranty->id,
                'claimed_by_party_id' => $completed[0]->job->customer_party_id,
                'description' => 'La fuite est réapparue au même endroit trois semaines après l’intervention.',
                'created_at' => now()->subDays(2),
            ]);
        }
    }

    /** Referral codes, a qualified referral and one flagged for review. */
    /**
     * @param  Collection<int, User>  $providers
     * @param  Collection<int, User>  $customers
     */
    private function growth(Collection $providers, Collection $customers): void
    {
        $referrer = $customers[0];
        $code = ReferralCode::query()->firstOrCreate(
            ['party_id' => $referrer->party_id],
            ['code' => 'HM-'.strtoupper(substr($referrer->party_id, 0, 6))],
        );

        Referral::query()->firstOrCreate(
            ['referee_party_id' => $customers[3]->party_id],
            [
                'referrer_party_id' => $referrer->party_id,
                'status' => 'qualified',
                'flagged_for_review' => false,
                'qualified_at' => now()->subDays(4),
                'created_at' => now()->subDays(12),
            ],
        );

        Referral::query()->firstOrCreate(
            ['referee_party_id' => $customers[4]->party_id],
            [
                'referrer_party_id' => $referrer->party_id,
                'status' => 'pending',
                'flagged_for_review' => true,
                'flag_reason' => 'Referrer exceeded the weekly velocity limit (6 claims in 7 days).',
                'created_at' => now()->subDays(2),
            ],
        );

        // A provider who has asked not to be contacted again by one of their past customers.
        DoNotContact::query()->firstOrCreate([
            'provider_party_id' => $providers[0]->party_id,
            'customer_party_id' => $customers[5]->party_id ?? $customers[1]->party_id,
        ]);

        unset($code);
    }

    /** Site visits, work sessions, job reports, deliverables, cash settlements, offers, shares. */
    /**
     * @param  Collection<int, User>  $providers
     * @param  Collection<int, User>  $customers
     */
    private function execution(Collection $providers, Collection $customers): void
    {
        $engagements = Engagement::query()->with(['job', 'assignments'])->latest('accepted_at')->take(4)->get();
        if ($engagements->isEmpty()) {
            return;
        }

        foreach ($engagements->take(2) as $i => $engagement) {
            $assignment = $engagement->assignments->first();
            if ($assignment === null) {
                continue;
            }

            // A closed session on one, an OPEN one on the other — an open check-in is what the
            // provider's "work in progress" screen and the overdue watchdog both key off.
            if (! WorkSession::query()->where('assignment_id', $assignment->id)->exists()) {
                WorkSession::factory()->create([
                    'assignment_id' => $assignment->id,
                    'started_at' => now()->subHours($i === 0 ? 26 : 3),
                    'ended_at' => $i === 0 ? now()->subHours(21) : null,
                ]);
            }

            // A job report hangs off the ASSIGNMENT (the human who did the work), not the
            // engagement — the same uniform assignment layer the worker app is built on.
            if ($i === 0 && ! JobReport::query()->where('assignment_id', $assignment->id)->exists()) {
                JobReport::factory()->create([
                    'assignment_id' => $assignment->id,
                    'summary' => 'Remplacement du joint et du flexible sous l’évier. Test de mise en eau concluant, aucune fuite résiduelle.',
                    'extra_charges_minor' => 15_000,
                    'submitted_at' => now()->subHours(20),
                ]);
            }
        }

        // A remote engagement's deliverable awaiting review. Queried rather than filtered from the
        // handful above: the remote engagements are not necessarily the most recent ones, and
        // searching only a slice is how this ended up seeding nothing at all.
        $remote = Engagement::query()
            ->whereHas('job', fn ($q) => $q->where('engagement_mode', 'remote'))
            ->first();
        if ($remote !== null && ! Deliverable::query()->where('engagement_id', $remote->id)->exists()) {
            Deliverable::factory()->create([
                'engagement_id' => $remote->id,
                'title' => 'Logo final — 3 déclinaisons (PNG, SVG, PDF)',
                'status' => 'submitted',
                'submitted_at' => now()->subHours(30),
            ]);
        }

        // A chargeable site visit that turned into a quote — the credit path (P2.5-04).
        $openJob = Job::query()->where('status', 'open')->first();
        if ($openJob !== null && ! SiteVisit::query()->exists()) {
            SiteVisit::factory()->create([
                'job_id' => $openJob->id,
                'provider_party_id' => $providers[0]->party_id,
                'status' => 'completed',
                'is_chargeable' => true,
                'fee_minor' => 10_000,
                'scheduled_for' => now()->subDays(5),
                'completed_at' => now()->subDays(5)->addHours(2),
            ]);
        }

        // Direct offers on an open job, so the offers queue and the "awaiting an answer" pipeline
        // stage are not empty.
        if ($openJob !== null) {
            foreach ($providers->take(2) as $provider) {
                JobOffer::query()->firstOrCreate(
                    ['job_id' => $openJob->id, 'provider_party_id' => $provider->party_id],
                    [
                        'origin' => 'customer_direct',
                        'status' => 'pending',
                        'expires_at' => now()->addDays(2),
                    ],
                );
            }
        }

        // Cash settlement — honest self-reporting is first-class (P3-15).
        $completed = Engagement::query()->whereNotNull('completed_at')->first();
        if ($completed !== null && ! CashSettlement::query()->exists()) {
            CashSettlement::factory()->create([
                'engagement_id' => $completed->id,
                'party_id' => $completed->provider_party_id,
                'amount_minor' => 120_000,
                'commission_minor' => 18_000,
                'recorded_at' => now()->subDays(2),
            ]);
        }

        // A live share link for the public status page (P6-05).
        if (! EngagementShare::query()->exists()) {
            EngagementShare::factory()->create([
                'engagement_id' => $engagements[0]->id,
                'expires_at' => now()->addDay(),
            ]);
        }
    }

    /** Follow-ups across the states the dispatcher moves them through. */
    /**
     * @param  Collection<int, User>  $providers
     * @param  Collection<int, User>  $customers
     */
    private function lifecycle(Collection $providers, Collection $customers): void
    {
        if (FollowUp::query()->exists()) {
            return;
        }

        $target = $customers[0];
        // One row in each state the dispatcher can leave a follow-up in — including `suppressed`,
        // which is what a budget cap or a revoked marketing consent produces and is therefore the
        // state most worth being able to see.
        $rows = [
            ['kind' => 'review_request', 'status' => 'sent', 'channel' => 'push', 'when' => now()->subDays(2)],
            ['kind' => 'review_reminder', 'status' => 'scheduled', 'channel' => 'whatsapp', 'when' => now()->addDay()],
            ['kind' => 'maintenance_due', 'status' => 'suppressed', 'channel' => 'whatsapp', 'when' => now()->subDay()],
            ['kind' => 'payout_ready', 'status' => 'responded', 'channel' => 'push', 'when' => now()->subDays(3)],
        ];

        foreach ($rows as $i => $row) {
            FollowUp::factory()->create([
                'target_party_id' => $i === 3 ? $providers[0]->party_id : $target->party_id,
                'kind' => $row['kind'],
                'status' => $row['status'],
                'channel' => $row['channel'],
                'scheduled_for' => $row['when'],
                'sent_at' => in_array($row['status'], ['sent', 'responded'], true) ? $row['when'] : null,
                'responded_at' => $row['status'] === 'responded' ? $row['when']->copy()->addHours(2) : null,
                'dedupe_key' => $row['kind'].':demo:'.$i,
            ]);
        }
    }
}
