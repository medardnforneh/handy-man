<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Models\Block;
use InvalidArgumentException;

/**
 * One party blocks another (build plan P6-07). Idempotent. The block is a hard boundary honoured in
 * search, ranking and offers — see {@see Block}.
 */
final class BlockParty
{
    public function handle(string $partyId, string $blockedPartyId): Block
    {
        if ($partyId === $blockedPartyId) {
            throw new InvalidArgumentException('A party cannot block itself.');
        }

        return Block::query()->firstOrCreate(
            ['party_id' => $partyId, 'blocked_party_id' => $blockedPartyId],
            ['created_at' => now()],
        );
    }
}
