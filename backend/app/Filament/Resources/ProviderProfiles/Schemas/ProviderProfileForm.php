<?php

namespace App\Filament\Resources\ProviderProfiles\Schemas;

use App\Models\Party;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The provider profile as staff may edit it.
 *
 * This shipped as the Filament scaffold — `->components([])` with a `//` in it — so Create and Edit
 * both opened on an empty page with a Save button.
 *
 * What staff may change here is deliberately narrow. A provider profile carries three kinds of
 * field and only one of them belongs in a form:
 *
 *  - **Written by the provider** (headline, bio, the channels they accept). Editable, because
 *    moderation is a real job: a headline can carry a phone number, an insult or a competitor's
 *    name, and someone has to be able to take it down without deleting the account.
 *  - **Earned** (rating, jobs completed). Derived from reviews and engagements and recomputed;
 *    the column is a cache, never the source of truth. Shown, never editable.
 *  - **Granted** (verification tier). This is the fact that gates paid on-site work (P0-17/P6-03),
 *    and it is raised by APPROVING a verification document, which records who approved what and
 *    when. A number typed into a form here would grant the same capability with no such trail, so
 *    it is shown and disabled with a pointer to the queue that does move it.
 */
class ProviderProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.provider.who'))
                ->description(__('admin.provider.who_hint'))
                ->schema([
                    // A profile belongs to exactly one party (`party_id` is UNIQUE) and moving it to
                    // another person would hand them someone else's history. Chosen once, then locked.
                    Select::make('party_id')
                        ->label(__('admin.provider.party'))
                        ->helperText(__('admin.provider.party_hint'))
                        ->options(fn (): array => Party::query()
                            ->orderBy('display_name')
                            ->limit(50)
                            ->pluck('display_name', 'id')
                            ->all())
                        ->getSearchResultsUsing(fn (string $search): array => Party::query()
                            ->where('display_name', 'ilike', "%{$search}%")
                            ->orderBy('display_name')
                            ->limit(50)
                            ->pluck('display_name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Party::query()->find($value)?->display_name)
                        ->searchable()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->native(false)
                        ->disabled(fn (?string $operation): bool => $operation === 'edit')
                        ->dehydrated(fn (?string $operation): bool => $operation !== 'edit'),
                ]),

            Section::make(__('admin.provider.public'))
                ->description(__('admin.provider.public_hint'))
                ->schema([
                    // The headline is what a customer sees BEFORE any engagement exists — it stands in
                    // for the provider's name on search results and quotes (P2-03), which is exactly
                    // why it is the field worth moderating.
                    TextInput::make('headline')
                        ->label(__('admin.provider.headline'))
                        ->helperText(__('admin.provider.headline_hint'))
                        ->maxLength(160),
                    Textarea::make('bio')
                        ->label(__('admin.provider.bio'))
                        ->rows(4)
                        ->maxLength(2000),
                    // Free text has a language of its own, independent of the reader's (doc 09).
                    Select::make('bio_language')
                        ->label(__('admin.provider.bio_language'))
                        ->helperText(__('admin.provider.bio_language_hint'))
                        ->options(['fr' => __('language.french'), 'en' => __('language.english')])
                        ->native(false),
                ]),

            Section::make(__('admin.provider.channels'))
                ->description(__('admin.provider.channels_hint'))
                ->columns(3)
                ->schema([
                    Toggle::make('accepts_direct')->label(__('admin.provider.accepts_direct')),
                    Toggle::make('accepts_dispatch')->label(__('admin.provider.accepts_dispatch')),
                    Toggle::make('accepts_bidding')->label(__('admin.provider.accepts_bidding')),
                ]),

            // Shown so the record reads as a whole, disabled so this page cannot become a second,
            // untraceable way to grant a capability or invent a reputation.
            Section::make(__('admin.provider.elsewhere'))
                ->description(__('admin.provider.elsewhere_hint'))
                ->columns(3)
                ->hiddenOn('create')
                ->schema([
                    TextInput::make('verification_tier')
                        ->label(__('admin.provider.tier'))
                        ->helperText(__('admin.provider.tier_hint'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('rating_avg')
                        ->label(__('admin.provider.rating'))
                        ->helperText(__('admin.provider.derived_hint'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('jobs_completed')
                        ->label(__('admin.provider.jobs_completed'))
                        ->helperText(__('admin.provider.derived_hint'))
                        ->disabled()
                        ->dehydrated(false),
                ]),
        ]);
    }
}
