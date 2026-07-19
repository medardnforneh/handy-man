<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

/**
 * Where the work happens (doc 06). This is the axis that makes geography optional: a remote job has
 * no address, no dispatch radius, no check-in, no panic. The feature applicability matrix lives in
 * App\Domain\Jobs\EngagementModePolicy (P2-02) — branch on that object, never on scattered ifs.
 */
enum EngagementMode: string
{
    case Onsite = 'onsite';
    case Remote = 'remote';
    case Hybrid = 'hybrid';

    // Deliberately NO feature logic here — "does this mode support X?" belongs to
    // App\Domain\Jobs\EngagementModePolicy, the single place that branches on mode.
}
