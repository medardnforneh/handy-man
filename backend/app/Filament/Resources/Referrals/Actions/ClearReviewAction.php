<?php

namespace App\Filament\Resources\Referrals\Actions;

use App\Domain\Referrals\ReferralService;
use App\Models\Referral;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Clear a velocity flag, defined once for both the queue row and the detail page. Clearing
 * qualifies the referral immediately if the referee has already completed a paid job (P8-02).
 */
final class ClearReviewAction
{
    public static function make(): Action
    {
        return Action::make('clear')
            ->label(__('admin.referral.clear'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('admin.referral.clear_description'))
            ->visible(fn (Referral $record): bool => $record->flagged_for_review)
            ->action(function (Referral $record): void {
                app(ReferralService::class)->clearReview($record);
                Notification::make()->title(__('admin.referral.cleared'))->success()->send();
            });
    }
}
