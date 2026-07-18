<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A domain exception that knows how to render itself as an RFC 7807 problem+json. One render
 * handler in bootstrap/app.php serves all of them, so each new domain error just implements this
 * instead of needing its own handler.
 */
interface ProblemAware
{
    public function problemType(): string;

    public function problemTitle(): string;

    public function problemStatus(): int;
}
