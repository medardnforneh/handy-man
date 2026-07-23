<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\FollowUps\FollowUpChannel;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpStatus;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FollowUp>
 */
final class FollowUpFactory extends Factory
{
    protected $model = FollowUp::class;

    /**
     * @return array<model-property<FollowUp>, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'kind' => FollowUpKind::ReviewRequest->value,
            'target_user_id' => $user->id,
            'target_party_id' => $user->party_id,
            'channel' => FollowUpChannel::Push->value,
            'scheduled_for' => now(),
            'status' => FollowUpStatus::Scheduled->value,
            'dedupe_key' => 'test:'.Str::uuid()->toString(),
        ];
    }
}
