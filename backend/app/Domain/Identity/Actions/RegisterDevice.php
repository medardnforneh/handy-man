<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registers or updates the caller's device (build plan P1-04). The device id is the client's stable
 * X-Device-Id, so this is an upsert — re-registering the same device updates its push token, app
 * version and last-seen time rather than creating a duplicate.
 */
final class RegisterDevice
{
    public function handle(User $user, string $deviceId, string $platform, ?string $pushToken, string $appVersion): Device
    {
        return DB::transaction(function () use ($user, $deviceId, $platform, $pushToken, $appVersion): Device {
            // A push token belongs to one device — if it moved here, release it from siblings so the
            // (user_id, push_token) unique constraint holds.
            if ($pushToken !== null) {
                Device::query()
                    ->where('user_id', $user->getKey())
                    ->where('push_token', $pushToken)
                    ->whereKeyNot($deviceId)
                    ->update(['push_token' => null]);
            }

            return Device::query()->updateOrCreate(
                ['id' => $deviceId],
                [
                    'user_id' => $user->getKey(),
                    'platform' => $platform,
                    'push_token' => $pushToken,
                    'app_version' => $appVersion,
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ],
            );
        });
    }
}
