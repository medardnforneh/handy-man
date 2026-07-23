<?php

declare(strict_types=1);

namespace App\Domain\Safety;

/**
 * The kind of safety alert (doc 02/04). `panic` is the button; `check_in_overdue` is raised by the
 * watchdog (P6-06); the rest can be raised manually from a report or by staff.
 */
enum SafetyAlertKind: string
{
    case Panic = 'panic';
    case NoShow = 'no_show';
    case UnsafeSite = 'unsafe_site';
    case Harassment = 'harassment';
    case CheckInOverdue = 'check_in_overdue';
}
