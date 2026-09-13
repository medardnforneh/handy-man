<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\CreateAddress;
use App\Domain\Identity\Actions\RecordConsent;
use App\Domain\Identity\Actions\RequestOtp;
use App\Domain\Identity\Actions\VerifyOtp;
use App\Domain\Identity\OtpException;
use App\Domain\Jobs\Actions\CreateJob;
use App\Domain\Jobs\Actions\PublishJob;
use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\EngagementModePolicy;
use App\Http\Requests\Web\ConfirmQuoteRequest;
use App\Http\Requests\Web\StartQuoteRequest;
use App\Models\Job;
use App\Models\Skill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Requesting a quote from the public site, without the app (launch checklist, doc 05; doc 08).
 *
 * Three server-rendered steps, no JavaScript required: the request form on a trade page, the
 * one-time code that proves the phone, and the confirmation with the job's reference. Under it
 * are the SAME actions the app calls — the OTP challenge, the find-or-create user, consent, the
 * address, the job and its publication — so a request posted from a browser on a feature phone
 * is indistinguishable, to providers and to the ledger, from one posted in the app. What differs
 * is only where the customer hears back: the follow-up engine already reaches a phone with no
 * app installed (WhatsApp, then SMS), and the confirmation says so.
 *
 * The draft lives in the session between steps, keyed to the trade so a second tab on another
 * trade cannot verify the wrong request.
 */
final class PublicQuoteRequestController extends Controller
{
    private const SESSION_KEY = 'quote_request';

    /** The form, on a leaf trade only — a category is not a thing one can be quoted for. */
    public function create(string $slug): View
    {
        $skill = $this->leaf($slug);

        return view('public.request', [
            'locale' => app()->getLocale(),
            'skill' => $skill,
            'cities' => $this->cities(),
        ]);
    }

    /** Validate the request, send the code, park the draft. */
    public function store(StartQuoteRequest $request, string $slug, RequestOtp $otp): RedirectResponse
    {
        $skill = $this->leaf($slug);
        $data = $request->validated();

        try {
            $issued = $otp->handle(
                phoneE164: $data['phone_e164'],
                purpose: 'login',
                ip: $request->ip(),
                deviceId: null,
            );
        } catch (OtpException $e) {
            return back()->withInput()->withErrors(['phone_e164' => __('request.error_'.$this->otpErrorKey($e))]);
        }

        $draft = ['skill_id' => $skill->id, 'slug' => $slug] + $data;
        // The same, and only, exception the API makes (OtpController::request): on a developer's
        // own machine the code exists nowhere but a log file, so the verify page shows it. Gated on
        // the ENVIRONMENT, never a flag — anywhere but `local` the draft never carries it.
        if (app()->environment('local')) {
            $draft['dev_code'] = $issued->code;
        }
        $request->session()->put(self::SESSION_KEY, $draft);

        return redirect()->route('services.request.verify', ['slug' => $slug]);
    }

    /** The code entry. */
    public function verify(Request $request, string $slug): View|RedirectResponse
    {
        $skill = $this->leaf($slug);
        $draft = $this->draft($request, $slug);
        if ($draft === null) {
            return redirect()->route('services.request', ['slug' => $slug]);
        }

        return view('public.request-verify', [
            'skill' => $skill,
            'phone' => $draft['phone_e164'],
            'devCode' => $draft['dev_code'] ?? null,
        ]);
    }

    /** Prove the phone, then post the job through the actions the app uses. */
    public function confirm(
        ConfirmQuoteRequest $request,
        string $slug,
        VerifyOtp $verify,
        RecordConsent $consent,
        CreateAddress $createAddress,
        CreateJob $createJob,
        PublishJob $publish,
        EngagementModePolicy $modes,
    ): RedirectResponse {
        $skill = $this->leaf($slug);
        $draft = $this->draft($request, $slug);
        if ($draft === null) {
            return redirect()->route('services.request', ['slug' => $slug]);
        }

        try {
            ['user' => $user] = $verify->handle($draft['phone_e164'], $request->validated('code'), 'login');
        } catch (OtpException $e) {
            return back()->withErrors(['code' => __('request.error_'.$this->otpErrorKey($e))]);
        }

        $locale = app()->getLocale();
        // Whether the request needs a place is the mode policy's call (doc 06), never a string test.
        $onSite = $modes->requiresAddress(EngagementMode::from($draft['engagement_mode']));

        $job = DB::transaction(function () use ($draft, $skill, $user, $locale, $onSite, $consent, $createAddress, $createJob, $publish): Job {
            // The form presented the terms and privacy notice in the locale it rendered in, and the
            // customer ticked them; that is the consent record (doc 04), same as the app's.
            $consent->handle($user, 'terms', true, $locale);
            $consent->handle($user, 'privacy', true, $locale);

            $addressId = null;
            if ($onSite) {
                $consent->handle($user, 'location_tracking', true, $locale);
                $city = config('cities')[$draft['city']];
                $address = $createAddress->handle($user, [
                    'line1' => $draft['line1'],
                    'quarter' => $draft['quarter'] ?? null,
                    'city' => $city['fr'],
                    'region' => $city['region'],
                ], (float) $city['lat'], (float) $city['lng']);
                $addressId = $address->id;
            }

            $job = $createJob->handle($user, [
                'skill_id' => $skill->id,
                'engagement_mode' => $draft['engagement_mode'],
                'title' => $draft['title'],
                'description' => $draft['description'] ?? null,
                'description_language' => $locale,
                'address_id' => $addressId,
                'price_model' => 'quote_only',
            ]);

            return $publish->handle($job);
        });

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->flash('posted_reference', $job->reference);

        return redirect()->route('services.request.posted', ['slug' => $slug]);
    }

    /** The confirmation — reachable once, right after posting; a reload goes back to the trade. */
    public function posted(Request $request, string $slug): View|RedirectResponse
    {
        $skill = $this->leaf($slug);
        $reference = $request->session()->get('posted_reference');
        if (! is_string($reference)) {
            return redirect()->route('services.show', ['slug' => $slug]);
        }

        return view('public.request-posted', ['skill' => $skill, 'reference' => $reference]);
    }

    private function leaf(string $slug): Skill
    {
        $skill = Skill::query()->where('slug', $slug)->where('is_leaf', true)->first();
        if ($skill === null) {
            throw new NotFoundHttpException('Unknown service.');
        }

        return $skill;
    }

    /** @return array<string, mixed>|null */
    private function draft(Request $request, string $slug): ?array
    {
        $draft = $request->session()->get(self::SESSION_KEY);

        return is_array($draft) && ($draft['slug'] ?? null) === $slug ? $draft : null;
    }

    /** @return array<string, string> key => label in the current locale */
    private function cities(): array
    {
        $locale = app()->getLocale();
        /** @var array<string, array{fr: string, en: string, lat: float, lng: float, region: string}> $cities */
        $cities = config('cities');

        return array_map(fn (array $c): string => $c[$locale] ?? $c['fr'], $cities);
    }

    private function otpErrorKey(OtpException $e): string
    {
        return match ($e->problemType()) {
            'otp-rate-limited' => 'rate_limited',
            'otp-locked' => 'locked',
            default => 'invalid_code',
        };
    }
}
