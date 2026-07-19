<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Models\Address;
use App\Models\Consent;
use App\Models\Device;
use App\Models\ProviderProfile;
use App\Models\User;

/**
 * DSAR export (build plan P1-10, doc 04 — data subject's right of access). Gathers everything we
 * hold about the user into a portable structure. Built from day one because retrofitting "give this
 * human all their data" later is painful.
 */
final class ExportPersonalData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $party = $user->party;

        return [
            'exported_at' => now()->toIso8601String(),
            'identity' => [
                'display_name' => $party->display_name,
                'phone_e164' => $user->phone_e164,
                'email' => $user->email,
                'locale' => $user->locale,
                'comms_locale' => $user->comms_locale,
                'status' => $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'addresses' => Address::query()->where('party_id', $party->id)->get()->map(fn (Address $a): array => [
                'label' => $a->label,
                'line1' => $a->line1,
                'quarter' => $a->quarter,
                'city' => $a->city,
                'region' => $a->region,
                'country_code' => $a->country_code,
                'latitude' => $a->point->latitude,
                'longitude' => $a->point->longitude,
            ])->all(),
            'consents' => Consent::query()->where('user_id', $user->getKey())->orderBy('created_at')
                ->get()->map(fn (Consent $c): array => [
                    'purpose' => $c->purpose,
                    'granted' => $c->granted,
                    'policy_version' => $c->policy_version,
                    'presented_locale' => $c->presented_locale,
                    'recorded_at' => $c->created_at->toIso8601String(),
                ])->all(),
            'devices' => Device::query()->where('user_id', $user->getKey())->get()->map(fn (Device $d): array => [
                'platform' => $d->platform,
                'app_version' => $d->app_version,
                'last_seen_at' => $d->last_seen_at?->toIso8601String(),
            ])->all(),
            'provider_profile' => ProviderProfile::query()->where('party_id', $party->id)->first()?->only([
                'headline', 'bio', 'verification_tier', 'jobs_completed',
            ]),
        ];
    }
}
