<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Models\Block;

/**
 * A party lifts a block it previously placed (build plan P6-07). Only the direction the caller owns
 * is removed — a block the other party placed is theirs to lift.
 */
final class UnblockParty
{
    public function handle(string $partyId, string $blockedPartyId): void
    {
        Block::query()
            ->where('party_id', $partyId)
            ->where('blocked_party_id', $blockedPartyId)
            ->delete();
    }
}
