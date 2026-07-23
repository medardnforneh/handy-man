<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Execution\Actions\CheckIn;
use App\Domain\Workspace\MessageKind;

/**
 * The structured status signals a working provider emits into the workspace timeline (build plan
 * P5-06, doc 06). Each maps to a narrated {@see MessageKind} — the chat *is* the state machine, so
 * marking "on the way" is a message, not a hidden flag. `arrived` is intentionally absent: it is
 * emitted by the geo check-in ({@see CheckIn}), not this free signal.
 */
enum ProviderStatus: string
{
    case OnTheWay = 'on_the_way';
    case Started = 'started';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Completed = 'completed';

    public function messageKind(): MessageKind
    {
        return match ($this) {
            self::OnTheWay => MessageKind::OnTheWay,
            self::Started => MessageKind::Started,
            self::Paused => MessageKind::Paused,
            self::Resumed => MessageKind::Resumed,
            self::Completed => MessageKind::Completed,
        };
    }
}
