<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

/**
 * How a job gets matched to a provider (doc 02). Phase 2 ships `direct` only; dispatch and bidding
 * are enabled by config once supply density supports them (doc 01 §4, build plan P8).
 */
enum AssignmentMode: string
{
    case Direct = 'direct';
    case Dispatch = 'dispatch';
    case Bidding = 'bidding';
}
