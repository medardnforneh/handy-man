<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Support\ProblemAware;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A manual re-engagement nudge was aimed at someone who is not this provider's customer (build plan
 * P7-08).
 *
 * `POST /provider/customers/{party}/follow-up` resolved ANY party id and the Action checked only
 * the provider's own do-not-contact list — a list the provider controls, not the customer. So any
 * authenticated user could send a stranger an SMS, a WhatsApp message and a push, on a party id
 * that is guessable from any page showing one, billed to the platform. The budget bounded the
 * volume; nothing bounded who.
 *
 * The client book is defined by an engagement having happened ({@see ProviderCustomers}), so that
 * is the same definition enforced here: no shared engagement, no nudge.
 */
final class NotYourCustomer extends RuntimeException implements ProblemAware
{
    public function __construct()
    {
        parent::__construct('You can only follow up with someone you have worked with.');
    }

    public function problemType(): string
    {
        return 'not-your-customer';
    }

    public function problemTitle(): string
    {
        return 'Not one of your customers';
    }

    public function problemStatus(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
