<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Support\ProblemAware;
use App\Support\ProvidesProblemExtras;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account that may not hold a session tried to get one, or tried to use one it still held
 * (suspended by staff, or closed by erasure).
 *
 * 403, not 401: 401 means "authenticate and try again", which is what every client does with it —
 * the app would send them back through the OTP screens to be refused again, in a loop. This is a
 * decision about the account, and re-authenticating cannot change it.
 *
 * `account_status` is on the payload so the app can say the true thing ("this account is
 * suspended — contact support") rather than a generic refusal, and support can be reached from the
 * screen that blocked them.
 */
final class AccountNotActive extends RuntimeException implements ProblemAware, ProvidesProblemExtras
{
    public function __construct(public readonly AccountStatus $status)
    {
        parent::__construct($status === AccountStatus::Suspended
            ? 'This account is suspended. Contact support.'
            : 'This account is closed.');
    }

    public function problemType(): string
    {
        return 'account-not-active';
    }

    public function problemTitle(): string
    {
        return $this->status === AccountStatus::Suspended ? 'Account suspended' : 'Account closed';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    /**
     * @return array<string, mixed>
     */
    public function problemExtras(): array
    {
        return ['account_status' => $this->status->value];
    }
}
