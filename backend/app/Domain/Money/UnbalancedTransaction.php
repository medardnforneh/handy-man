<?php

declare(strict_types=1);

namespace App\Domain\Money;

use RuntimeException;

/**
 * A programming error: an attempt to post a ledger transaction whose debits don't equal its credits
 * (or with fewer than two legs). The DB would reject it anyway (the balance constraint) — this
 * catches it earlier, at the call site, with a clearer message. Not a client-facing problem.
 */
final class UnbalancedTransaction extends RuntimeException {}
