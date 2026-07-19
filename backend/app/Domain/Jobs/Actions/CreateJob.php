<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\EngagementMode;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a job as a draft (build plan P2-03). Address requirement per mode is guaranteed by the DB
 * CHECK (doc 06); this just assembles the row and any posting photos.
 *
 * @phpstan-type JobData array{skill_id: string, engagement_mode: string, title: string, address_id?: ?string, description?: ?string, description_language?: ?string, price_model?: string, budget_minor?: ?int, urgency?: int, photos?: list<string>}
 */
final class CreateJob
{
    /**
     * @param  JobData  $data
     */
    public function handle(User $customer, array $data): Job
    {
        return DB::transaction(function () use ($customer, $data): Job {
            $job = Job::query()->create([
                'reference' => $this->generateReference(),
                'customer_party_id' => $customer->party_id,
                'created_by_user_id' => $customer->getKey(),
                'skill_id' => $data['skill_id'],
                'address_id' => $data['address_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'description_language' => $data['description_language'] ?? $customer->locale,
                'engagement_mode' => EngagementMode::from($data['engagement_mode'])->value,
                'assignment_mode' => 'direct',
                'price_model' => $data['price_model'] ?? 'quote_only',
                'budget_minor' => $data['budget_minor'] ?? null,
                'currency' => 'XAF',
                'urgency' => $data['urgency'] ?? 1,
                'status' => 'draft',
            ]);

            foreach ($data['photos'] ?? [] as $position => $path) {
                JobPhoto::query()->create([
                    'job_id' => $job->id,
                    'path' => $path,
                    'position' => $position,
                    'created_at' => now(),
                ]);
            }

            return $job;
        });
    }

    private function generateReference(): string
    {
        // Human-quotable, unambiguous (no 0/O/1/I): e.g. JOB-7K2M9.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

        do {
            $code = 'JOB-'.collect(range(1, 5))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (Job::query()->where('reference', $code)->exists());

        return $code;
    }
}
