<?php

namespace App\Filament\Resources\Parties\Actions;

use App\Domain\Access\AccountStatus;
use App\Domain\Access\Actions\SetAccountStatus;
use App\Models\Party;
use App\Models\User;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Suspend and reinstate, on the party list row and the detail page.
 *
 * This is the missing half of the report queue. `ReviewReport` records a decision and explicitly
 * does nothing else — which left staff able to conclude that a provider was dangerous and unable to
 * act on it, because nothing in the panel wrote `status` and nothing in the app read it.
 *
 * Both actions route through the {@see SetAccountStatus} Action, never the model, so the status
 * machine, the session revocation, the provider-profile flag, the activity log and the outbox
 * announcement all happen wherever suspension is triggered from (rule #8).
 *
 * A reason is REQUIRED in both directions. It is the only account of why someone lost their
 * livelihood on this platform, or got it back, and it is what the activity log carries.
 */
final class AccountStatusActions
{
    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->label(__('admin.party.suspend'))
            ->icon(LucideIcon::Ban)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('admin.party.suspend_explainer'))
            ->visible(fn (Party $record): bool => AccountStatus::from($record->status)->canAuthenticate())
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin.party.status_reason'))
                    ->helperText(__('admin.party.status_reason_hint'))
                    ->required()
                    ->maxLength(5000),
            ])
            ->action(function (Party $record, array $data): void {
                /** @var User $admin */
                $admin = Auth::user();

                app(SetAccountStatus::class)->suspend($record, $admin, (string) $data['reason'], Request::ip());

                Notification::make()->title(__('admin.party.suspended_notice'))->success()->send();
            });
    }

    public static function reinstate(): Action
    {
        return Action::make('reinstate')
            ->label(__('admin.party.reinstate'))
            ->icon(LucideIcon::RotateCcw)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('admin.party.reinstate_explainer'))
            // Only from `suspended`. `closed` is terminal (erasure destroyed the identity), so the
            // button is absent rather than present-and-refused.
            ->visible(fn (Party $record): bool => AccountStatus::from($record->status) === AccountStatus::Suspended)
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin.party.status_reason'))
                    ->required()
                    ->maxLength(5000),
            ])
            ->action(function (Party $record, array $data): void {
                /** @var User $admin */
                $admin = Auth::user();

                app(SetAccountStatus::class)->reinstate($record, $admin, (string) $data['reason'], Request::ip());

                Notification::make()->title(__('admin.party.reinstated_notice'))->success()->send();
            });
    }
}
