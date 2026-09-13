<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\EngagementModePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The public quote-request form (PublicQuoteRequestController::store). The same shape the app's
 * post-a-request form submits, minus what a browser without JavaScript cannot supply — a GPS
 * fix — which the city stands in for. On-site needs a place and the location consent; remote
 * needs neither (the conditional-address rule, doc 06).
 */
final class StartQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Whether the request needs a place is the mode policy's call (doc 06, P2-02): the form
        // offers the two modes a browser can post, and asks the policy what each one requires.
        $mode = EngagementMode::tryFrom((string) $this->input('engagement_mode', ''));
        $needsPlace = $mode !== null && app(EngagementModePolicy::class)->requiresAddress($mode);
        $offered = [EngagementMode::Onsite->value, EngagementMode::Remote->value];

        return [
            'phone_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'engagement_mode' => ['required', Rule::in($offered)],
            'title' => ['required', 'string', 'min:4', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => [Rule::requiredIf($needsPlace), 'nullable', Rule::in(array_keys(config('cities')))],
            'line1' => [Rule::requiredIf($needsPlace), 'nullable', 'string', 'max:200'],
            'quarter' => ['nullable', 'string', 'max:120'],
            // `accepted` is an implicit rule — a missing field fails it — so the location consent is
            // required only when a place is, and a remote request need not carry the box at all.
            'consent_location' => $needsPlace ? ['accepted'] : ['nullable'],
            'consent_terms' => ['accepted'],
        ];
    }

    /**
     * The phone arrives however people write it — "6 99 00 01 11", "699000111", "+237 699…" —
     * and leaves as E.164. A number with no country code is a Cameroon number; that is the only
     * market this product serves, and asking a customer to type +237 is asking them to fail.
     */
    protected function prepareForValidation(): void
    {
        $raw = (string) $this->input('phone_e164', '');
        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';
        if ($digits !== '' && ! str_starts_with($digits, '+')) {
            $digits = str_starts_with($digits, '237') ? '+'.$digits : '+237'.$digits;
        }

        $this->merge(['phone_e164' => $digits]);
    }
}
