<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

/**
 * The kind of a conversation message (doc 06). Free-form kinds are posted by clients; structured
 * kinds are narrated by the server (the Action that performs the transition, in its transaction —
 * CLAUDE.md rule #11). A client may never post a structured kind.
 */
enum MessageKind: string
{
    // Free-form (client-postable).
    case Text = 'text';
    case Voice = 'voice';
    case Media = 'media';
    case Document = 'document';

    // Server-narrated.
    case System = 'system';
    case QuoteSubmitted = 'quote_submitted';
    case QuoteRevised = 'quote_revised';
    case QuoteAccepted = 'quote_accepted';
    case QuoteRejected = 'quote_rejected';
    case MilestoneSubmitted = 'milestone_submitted';
    case MilestoneApproved = 'milestone_approved';
    case MilestoneRejected = 'milestone_rejected';
    case SiteVisitProposed = 'site_visit_proposed';
    case SiteVisitConfirmed = 'site_visit_confirmed';
    case OnTheWay = 'on_the_way';
    case Arrived = 'arrived';
    case Started = 'started';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Completed = 'completed';
    case PaymentRequested = 'payment_requested';
    case PaymentReceived = 'payment_received';
    case DeliverableSubmitted = 'deliverable_submitted';

    /**
     * Free-form kinds a client is allowed to post directly. Everything else is narrated by the server.
     */
    public function isClientPostable(): bool
    {
        return match ($this) {
            self::Text, self::Voice, self::Media, self::Document => true,
            default => false,
        };
    }
}
