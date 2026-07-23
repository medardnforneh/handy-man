<?php

declare(strict_types=1);

namespace App\Domain\Safety\Actions;

use App\Domain\Notifications\SmsSender;
use App\Domain\Safety\SafetyAlertKind;
use App\Models\EmergencyContact;
use App\Models\SafetyAlert;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Throwable;

/**
 * The panic button (build plan P6-04, doc 04). One request from the app raises a `safety_alert`,
 * texts the user's emergency contacts, and alerts staff (via the outbox). Everything is server-side,
 * so it works with the app backgrounded — the phone only has to land the single request. Emergency
 * SMS is sent directly (not via the relay), because a panic must not wait for a queue cadence.
 */
final class RaisePanicAlert
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly SmsSender $sms,
    ) {}

    public function handle(User $user, ?float $latitude = null, ?float $longitude = null, ?string $note = null, ?string $assignmentId = null): SafetyAlert
    {
        $point = $latitude !== null && $longitude !== null
            ? new Point($latitude, $longitude, Srid::WGS84->value)
            : null;

        $alert = DB::transaction(function () use ($user, $assignmentId, $point, $note): SafetyAlert {
            $alert = SafetyAlert::query()->create([
                'user_id' => $user->id,
                'assignment_id' => $assignmentId,
                'kind' => SafetyAlertKind::Panic->value,
                'point' => $point,
                'note' => $note,
                'status' => 'open',
                'created_at' => now(),
            ]);

            $this->outbox->publish('safety.alert_raised', [
                'alert_id' => $alert->id,
                'user_id' => $user->id,
                'kind' => SafetyAlertKind::Panic->value,
            ]);

            return $alert;
        });

        $this->textEmergencyContacts($user, $latitude, $longitude);

        return $alert;
    }

    private function textEmergencyContacts(User $user, ?float $latitude, ?float $longitude): void
    {
        $contacts = EmergencyContact::query()->where('user_id', $user->id)->get();
        if ($contacts->isEmpty()) {
            return;
        }

        $locale = $user->comms_locale ?: (string) config('app.locale', 'en');
        $name = $user->party->display_name;
        $location = $latitude !== null && $longitude !== null
            ? "https://maps.google.com/?q={$latitude},{$longitude}"
            : '—';

        $message = (string) __('sms.panic_alert', ['name' => $name, 'location' => $location], $locale);

        foreach ($contacts as $contact) {
            try {
                $this->sms->send($contact->phone_e164, $message);
            } catch (Throwable) {
                // Best-effort: one unreachable contact must not stop the others (or the alert itself).
            }
        }
    }
}
