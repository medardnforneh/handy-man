<?php

namespace App\Filament\Resources\Referrals;

use App\Filament\Resources\Referrals\Pages\ListReferrals;
use App\Filament\Resources\Referrals\Pages\ViewReferral;
use App\Filament\Resources\Referrals\Schemas\ReferralInfolist;
use App\Filament\Resources\Referrals\Tables\ReferralsTable;
use App\Models\Referral;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The referral review queue (build plan P8-02). Referrals flagged by the velocity control wait here
 * for a human to clear (which qualifies them) — fraud control that flags, never silently auto-pays.
 */
class ReferralResource extends Resource
{
    protected static ?string $model = Referral::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Trust & safety';

    protected static ?string $recordTitleAttribute = 'id';

    /**
     * The queue showed both parties as truncated UUIDs, which is unusable for judging a referral —
     * name it by the two people involved.
     */
    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof Referral) {
            return null;
        }

        return trim($record->referrer->display_name.' → '.$record->referee->display_name);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReferralInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReferralsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $flagged = Referral::query()->where('flagged_for_review', true)->count();

        return $flagged > 0 ? (string) $flagged : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReferrals::route('/'),
            'view' => ViewReferral::route('/{record}'),
        ];
    }
}
