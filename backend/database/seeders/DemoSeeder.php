<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Jobs\JobStateMachine;
use App\Domain\Jobs\JobStatus;
use App\Domain\Money\Actions\ApproveMilestone;
use App\Domain\Money\Actions\InitiatePaymentIntent;
use App\Domain\Money\Actions\RaiseReconciliationException;
use App\Domain\Money\Actions\ReconcilePaymentIntent;
use App\Domain\Money\Actions\RequestPayout;
use App\Domain\Money\Actions\ResolvePayout;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Domain\Workspace\Actions\PostMessage;
use App\Models\Address;
use App\Models\Conversation;
use App\Models\Job;
use App\Models\Party;
use App\Models\ProviderProfile;
use App\Models\Quotation;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic demo data for the admin panel — a coherent Cameroon marketplace with money that actually
 * flows through the ledger (escrow funded, milestones released, credits bought, a payout, and one
 * reconciliation exception so "needs attention" isn't empty). Also seeds a loginable superadmin.
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoSeeder
 *
 * Log in at /admin with  admin@handyman.cm  /  password  (enrol 2FA on first login).
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(StaffRolesSeeder::class);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@handyman.cm'],
            [
                'party_id' => Party::factory()->individual()->create(['display_name' => 'Medard Nforneh'])->id,
                'phone_e164' => '+237600000001',
                'password_hash' => 'password',
                'locale' => 'en', 'comms_locale' => 'en', 'status' => 'active',
                'phone_verified_at' => now(), 'email_verified_at' => now(),
            ],
        );
        $admin->assignRole('superadmin');

        // Use the REAL taxonomy (P1-07), not throwaway factory skills — so the app's GET /skills and
        // the admin panel show a coherent catalog. SkillsSeeder is idempotent (firstOrCreate by slug).
        $this->call(SkillsSeeder::class);
        $skills = Skill::query()->where('is_leaf', true)->orderBy('name_en')->limit(5)->get();

        $providerNames = ['Atelier Nkeng', 'Douala Cool Services', 'Marie Fotso', 'BTP Cameroun SARL', 'Éric Kamga', 'Fresh Design Studio', 'Yaoundé Élec', 'Bâti-Pro'];
        $customerNames = ['Jean Mbarga', 'Aïcha Bello', 'Paul Etoundi', 'Grace Ngo', 'Samuel Tchoua', 'Fatou Sow', 'Restaurant Le Palmier', 'Boutique Kribi'];

        $providers = collect($providerNames)->map(function (string $name, int $i) {
            $user = $this->person($name, '+2376'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT));
            ProviderProfile::factory()->verified(2)->create(['party_id' => $user->party_id, 'headline' => $name]);

            return $user;
        });

        $customers = collect($customerNames)->map(fn (string $name, int $i) => $this->person($name, '+2376'.str_pad((string) (20000000 + $i), 8, '0', STR_PAD_LEFT)));

        // Fully engaged jobs with money flowing (quote → escrow → release), some advanced further.
        $scenarios = [
            [0, 0, 0, 900_000, 200_000, JobStatus::InProgress],
            [3, 5, 3, 450_000, 0, JobStatus::WorkSubmitted],
            [1, 1, 1, 1_250_000, 300_000, JobStatus::Engaged],
            [2, 3, 2, 3_100_000, 600_000, JobStatus::Completed],
            [4, 4, 4, 620_000, 120_000, JobStatus::InProgress],
            [1, 6, 1, 780_000, 150_000, JobStatus::Engaged],
        ];
        foreach ($scenarios as [$pi, $ci, $si, $subtotal, $deposit, $advanceTo]) {
            $this->engage($providers[$pi], $customers[$ci], $skills[$si], $subtotal, $deposit, $advanceTo);
        }

        // Some jobs left open / offered for the pipeline KPIs.
        foreach (range(0, 4) as $n) {
            Job::factory()->status($n % 2 === 0 ? JobStatus::Open : JobStatus::Offered)->create([
                'customer_party_id' => $customers[$n]->party_id,
                'created_by_user_id' => $customers[$n]->id,
                'skill_id' => $skills[$n % $skills->count()]->id,
                'address_id' => Address::factory()->create(['party_id' => $customers[$n]->party_id])->id,
                'title' => 'Nouvelle demande — '.$skills[$n % $skills->count()]->name_fr,
            ]);
        }

        // Lead-credit purchases for a couple of providers.
        foreach ([$providers[0], $providers[2]] as $pro) {
            $this->collect($pro, PaymentPurpose::LeadCredits, 500_000, null);
        }

        // A payout for a provider who now has a payable balance from released milestones.
        $payout = app(RequestPayout::class)->handle($providers[2], 100_000, $providers[2]->phone_e164, (string) Str::uuid());
        app(PaymentGateway::class)->settle($payout->external_ref, GatewayStatus::Succeeded);
        app(ResolvePayout::class)->handle($payout->fresh());

        // One open reconciliation exception so "needs attention" is populated.
        app(RaiseReconciliationException::class)->handle(
            kind: 'settlement_mismatch',
            detail: 'Ledger platform_cash is 2 000 under the CinetPay wallet report for '.now()->subDay()->format('d M').'.',
            amountMinor: 2_000,
        );

        $this->command->info('Demo data seeded. Log in at /admin with admin@handyman.cm / password.');
    }

    private function person(string $name, string $phone): User
    {
        $party = Party::factory()->individual()->create(['display_name' => $name]);

        return User::factory()->create([
            'party_id' => $party->id,
            'phone_e164' => $phone,
        ]);
    }

    private function engage(User $provider, User $customer, Skill $skill, int $subtotal, int $deposit, JobStatus $advanceTo): void
    {
        $onsite = $skill->name_fr !== 'Design graphique';
        $job = Job::factory()
            ->status(JobStatus::Open)
            ->when(! $onsite, fn ($f) => $f->remote())
            ->create([
                'customer_party_id' => $customer->party_id,
                'created_by_user_id' => $customer->id,
                'skill_id' => $skill->id,
                'address_id' => $onsite ? Address::factory()->create(['party_id' => $customer->party_id])->id : null,
                'title' => $skill->name_fr.' — '.fake()->words(2, true),
            ]);

        $quote = Quotation::factory()->submitted()->create([
            'job_id' => $job->id,
            'provider_party_id' => $provider->party_id,
            'subtotal_minor' => $subtotal,
            'deposit_minor' => $deposit,
            'valid_until' => now()->addDays(5),
        ]);

        $engagement = app(AcceptQuotation::class)->handle($customer, $quote);

        // Fund escrow for the whole agreed amount, then release the deposit milestone.
        $this->collect($customer, PaymentPurpose::Escrow, (int) $engagement->agreed_amount_minor, $engagement->id);
        $first = $engagement->milestones()->orderBy('position')->first();
        if ($first !== null) {
            app(ApproveMilestone::class)->handle($first);
        }

        // A little human chatter in the thread.
        $conversation = Conversation::query()->where('job_id', $job->id)->first();
        if ($conversation !== null) {
            app(PostMessage::class)->handle($customer, $conversation, 'Bonjour, quand pouvez-vous commencer ?');
            app(PostMessage::class)->handle($provider, $conversation, 'Bonjour, je peux passer demain matin.');
        }

        // Advance the job's lifecycle for variety.
        $sm = app(JobStateMachine::class);
        $path = [JobStatus::InProgress, JobStatus::WorkSubmitted, JobStatus::Completed];
        foreach ($path as $step) {
            if ($sm->canTransition($job->fresh()->status, $step)) {
                $sm->transition($job->fresh(), $step);
            }
            if ($step === $advanceTo) {
                break;
            }
        }
    }

    private function collect(User $payer, PaymentPurpose $purpose, int $amount, ?string $engagementId): void
    {
        $intent = app(InitiatePaymentIntent::class)->handle(
            $payer, $purpose, $amount, $payer->phone_e164, (string) Str::uuid(), $engagementId,
        );
        app(PaymentGateway::class)->settle($intent->external_ref, GatewayStatus::Succeeded);
        app(ReconcilePaymentIntent::class)->handle($intent->fresh());
    }
}
