<?php

declare(strict_types=1);

namespace App\Domain\Provider;

use App\Models\Block;
use App\Models\ProviderProfile;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Browsing providers WITHOUT a job (the app's discover rail).
 *
 * `ProviderSearch` answers "who can do THIS job" — it needs a job for the skill, the mode and the
 * address, and it is owner-gated. That is the wrong shape for a customer who is still deciding what
 * to ask for. This is the same idea one step earlier: browse a trade, see who does it.
 *
 * The listing rules are deliberately identical to the crawlable services directory
 * (`PublicServiceController`), because they are the same act — a stranger looking at who offers a
 * trade — and two different answers to that would be a bug waiting to happen: suspended providers
 * excluded, ranked by verification tier then shrunk rating.
 *
 * Geography is NOT a filter here. A discover rail is browsed before there is an address to match
 * against, and a service area is PII (P2-03) — filtering by one would mean asking for a location
 * the customer has no reason to give yet.
 */
final class PublicProviderDirectory
{
    /**
     * @param  string|null  $skillSlug  A leaf trade to filter by; null browses the whole pool.
     * @param  string|null  $viewerPartyId  The signed-in party, when there is one — used only to
     *                                      honour blocks. Anonymous browsing passes null.
     * @param  bool|null  $travels  True for providers who come to the customer, false for those who
     *                              work remotely, null for both. Deliberately a provider capability
     *                              rather than an engagement mode: modes belong to JOBS, and
     *                              branching on one outside `EngagementModePolicy` is exactly what
     *                              P2-02 forbids. The caller translates once, through the policy.
     * @return Collection<int, ProviderProfile>
     */
    public function browse(
        ?string $skillSlug = null,
        ?string $viewerPartyId = null,
        ?bool $travels = null,
        int $limit = 30,
    ): Collection {
        $query = ProviderProfile::query()
            ->whereNull('suspended_at')
            // Only providers who are actually open to being approached directly. Listing someone who
            // has switched that off would send the customer into a refusal they can't see coming.
            ->where('accepts_direct', true)
            ->withCount('serviceAreas')
            ->with(['skills.skill']);

        // Whether a provider travels is decided by whether they have declared any service area at
        // all — a yes/no about the KIND of work, which is why it can be filtered on publicly while
        // the areas themselves stay private (P2-03). The customer learns "they come to you", not
        // where they are.
        if ($travels === true) {
            $query->whereHas('serviceAreas');
        } elseif ($travels === false) {
            $query->whereDoesntHave('serviceAreas');
        }

        if ($skillSlug !== null) {
            $skill = Skill::query()->where('slug', $skillSlug)->first();
            if ($skill === null) {
                return new Collection; // an unknown trade has nobody, rather than everybody
            }
            // A category slug means "anyone who does any trade under it" — a customer browsing
            // "Plumbing" should not have to know which leaf their problem falls under.
            $skillIds = $skill->is_leaf
                ? [$skill->id]
                : $skill->children()->pluck('id')->push($skill->id)->all();

            $query->whereHas('skills', fn (Builder $q): Builder => $q->whereIn('skill_id', $skillIds));
        }

        // Blocks are honoured wherever a provider can surface (P6-07) — including here, where the
        // viewer is browsing rather than searching. A blocked party must not reappear just because
        // the customer took a different route to the same list.
        if ($viewerPartyId !== null) {
            $blocked = Block::partyIdsAround($viewerPartyId);
            if ($blocked !== []) {
                $query->whereNotIn('party_id', $blocked);
            }
            // Nor should a provider be shown their own card while browsing as a customer.
            $query->where('party_id', '!=', $viewerPartyId);
        }

        return $query
            ->orderByDesc('verification_tier')
            ->orderByDesc('rating_avg')
            ->limit($limit)
            ->get();
    }
}
