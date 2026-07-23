<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\FollowUps\Actions\ScheduleManualFollowUp;
use App\Domain\FollowUps\ProviderCustomers;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FollowUpResource;
use App\Models\DoNotContact;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The provider CRM surface (build plan P7-08). The provider's client book — customer list with
 * history + LTV, manual re-engagement (same budget/consent gates), and an absolute do-not-contact.
 */
final class ProviderCustomerController extends Controller
{
    public function index(Request $request, ProviderCustomers $customers): JsonResponse
    {
        return response()->json(['data' => $customers->forProvider($this->user($request)->party_id)]);
    }

    public function followUp(Request $request, string $party, ScheduleManualFollowUp $action): JsonResponse
    {
        $customer = $this->customerUser($party);

        // DoNotContactRefused (422) renders as problem+json automatically.
        $followUp = $action->handle($this->user($request), $customer);

        return FollowUpResource::make($followUp)->response()->setStatusCode(201);
    }

    public function setDoNotContact(Request $request, string $party): JsonResponse
    {
        DoNotContact::query()->firstOrCreate(
            ['provider_party_id' => $this->user($request)->party_id, 'customer_party_id' => $party],
            ['created_at' => now()],
        );

        return response()->json(['data' => ['status' => 'blocked']], 201);
    }

    public function removeDoNotContact(Request $request, string $party): JsonResponse
    {
        DoNotContact::query()
            ->where('provider_party_id', $this->user($request)->party_id)
            ->where('customer_party_id', $party)
            ->delete();

        return response()->json(['data' => ['status' => 'unblocked']]);
    }

    private function customerUser(string $partyId): User
    {
        return User::query()->where('party_id', $partyId)->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
