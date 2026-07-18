<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\RegisterDevice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Http\Resources\Api\V1\DeviceResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Device registration (build plan P1-04). Upserts the caller's device (keyed by X-Device-Id) with
 * its push token and app version.
 */
final class DeviceController extends Controller
{
    public function store(RegisterDeviceRequest $request, RegisterDevice $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $device = $action->handle(
            user: $user,
            deviceId: $request->string('device_id')->toString(),
            platform: $request->string('platform')->toString(),
            pushToken: $request->input('push_token'),
            appVersion: $request->string('app_version')->toString(),
        );

        return DeviceResource::make($device)->response()->setStatusCode(200);
    }
}
