<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Execution\WorkProgress;
use App\Domain\Jobs\EngagementModePolicy;
use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\Engagement;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The provider's execution view of one engagement (build plan P5-03/04/06) — everything the
 * work-detail screen needs in a single round-trip: who the customer is, where the work is, and the
 * current state of THIS worker's assignment.
 *
 * PII: the viewer is an assigned worker on a formed engagement, so the exact address is theirs to
 * see (the coarse-area rule of {@see JobResource} guards the PRE-engagement provider). Whether there
 * is an address at all is the {@see EngagementModePolicy}'s call, never an inline mode check.
 *
 * `supports_check_in` / `checked_in` / `current_status` / `report_submitted` are derived server-side
 * ({@see WorkProgress}) so the client offers only affordances the server would accept — a remote
 * engagement never renders a check-in button, and a closed session flips it back to "check in".
 *
 * @mixin Engagement
 */
final class ProviderWorkDetailResource extends JsonResource
{
    private Assignment $assignment;

    /** The caller's active assignment — the one whose execution state this resource reports. */
    public function forAssignment(Assignment $assignment): self
    {
        $this->assignment = $assignment;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Job $job */
        $job = $this->job;
        $modes = app(EngagementModePolicy::class);
        $progress = app(WorkProgress::class);

        $session = $progress->openSession($this->assignment);
        // A read never creates the conversation — before the first narration there simply is no status.
        $conversation = Conversation::query()->where('job_id', $job->id)->first();

        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'assignment_id' => $this->assignment->id,
            'reference' => $job->reference,
            'title' => $job->title,
            'description' => $job->description,
            'customer_name' => $job->customer?->display_name,
            'engagement_mode' => $job->engagement_mode->value,
            'job_status' => $job->status->value,
            'agreed_amount' => [
                'amount_minor' => $this->agreed_amount_minor,
                'currency' => $this->currency,
            ],
            'scheduled_from' => $this->assignment->scheduled_from?->toIso8601String(),
            'scheduled_to' => $this->assignment->scheduled_to?->toIso8601String(),
            'address' => $this->address($job, $modes),
            'supports_check_in' => $modes->supportsCheckIn($job->engagement_mode),
            'checked_in' => $session !== null,
            'checked_in_at' => $session?->started_at->toIso8601String(),
            'current_status' => $conversation === null
                ? null
                : $progress->currentStatus($this->assignment, $conversation->id)?->value,
            'report_submitted' => $progress->reportSubmitted($this->assignment),
            'accepted_at' => $this->accepted_at->toIso8601String(),
        ];
    }

    /**
     * The exact site address, or null for modes that have none. Only reached by an assigned worker.
     *
     * @return array<string, mixed>|null
     */
    private function address(Job $job, EngagementModePolicy $modes): ?array
    {
        if (! $modes->requiresAddress($job->engagement_mode)) {
            return null;
        }

        $address = $job->address;
        if ($address === null) {
            return null;
        }

        return [
            'line1' => $address->line1,
            'quarter' => $address->quarter,
            'city' => $address->city,
            'region' => $address->region,
            'country_code' => $address->country_code,
            'landmark_note' => $address->landmark_note,
            'latitude' => $address->point->latitude,
            'longitude' => $address->point->longitude,
        ];
    }
}
