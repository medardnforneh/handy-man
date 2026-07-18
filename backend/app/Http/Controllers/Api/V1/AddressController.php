<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\CreateAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateAddressRequest;
use App\Http\Resources\Api\V1\AddressResource;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Addresses (build plan P1-06). Creating one requires location_tracking consent (enforced in the
 * action). A user only ever sees their own addresses.
 */
final class AddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $addresses = Address::query()
            ->where('party_id', $user->party_id)
            ->latest('created_at')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(CreateAddressRequest $request, CreateAddress $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $address = $action->handle(
            user: $user,
            data: $request->safe()->except(['latitude', 'longitude']),
            latitude: (float) $request->float('latitude'),
            longitude: (float) $request->float('longitude'),
        );

        return AddressResource::make($address)->response()->setStatusCode(201);
    }
}
