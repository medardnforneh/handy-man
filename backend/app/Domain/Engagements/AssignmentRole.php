<?php

declare(strict_types=1);

namespace App\Domain\Engagements;

enum AssignmentRole: string
{
    case Lead = 'lead';
    case Helper = 'helper';
}
