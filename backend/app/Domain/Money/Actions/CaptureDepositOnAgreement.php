<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\PaymentPurpose;
use App\Models\Engagement;
use App\Models\PaymentIntent;

/**
 * Agreement-time deposit capture (build plan P3-13, doc 03). When an engagement forms with a
 * milestone plan, the money owed upfront (the position-0 milestone — the deposit, or the whole
 * amount for a single-payment plan) is collected into escrow immediately, rather than waiting for
 * the customer to fund it manually. It funds escrow so a provider knows the money is committed
 * before starting work; the escrow is released milestone-by-milestone via {@see ApproveMilestone}.
 *
 * The collection is idempotent on a deterministic key (`deposit-capture:{engagement}`), so the
 * at-least-once outbox seam that drives this (`engagement.created`) never charges twice. Offer-path
 * engagements carry no milestones, so they capture nothing here.
 *
 * MUST run outside the acceptance transaction (it calls the gateway) — hence it rides the relay.
 */
final class CaptureDepositOnAgreement
{
    public function __construct(private readonly InitiatePaymentIntent $initiate) {}

    public function handle(Engagement $engagement): ?PaymentIntent
    {
        $deposit = $engagement->milestones()->where('position', 0)->first();
        if ($deposit === null || $deposit->amount_minor <= 0) {
            return null; // offer-path (no milestones) or a zero deposit — nothing to collect
        }

        $customer = $engagement->job->customer->user;
        if ($customer === null) {
            return null; // no reachable payer (e.g. an erased party) — leave it to manual funding
        }

        return $this->initiate->handle(
            $customer,
            PaymentPurpose::Escrow,
            (int) $deposit->amount_minor,
            $customer->phone_e164,
            "deposit-capture:{$engagement->id}",
            $engagement->id,
            $engagement->currency,
        );
    }
}
