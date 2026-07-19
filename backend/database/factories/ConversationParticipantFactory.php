<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationParticipant>
 */
final class ConversationParticipantFactory extends Factory
{
    protected $model = ConversationParticipant::class;

    /**
     * @return array<model-property<ConversationParticipant>, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'party_id' => Party::factory()->individual(),
            'user_id' => User::factory(),
            'joined_at' => now(),
        ];
    }
}
