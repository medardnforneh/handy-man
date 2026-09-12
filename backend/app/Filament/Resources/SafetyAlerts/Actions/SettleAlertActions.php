<?php

namespace App\Filament\Resources\SafetyAlerts\Actions;

use App\Domain\Safety\Actions\ResolveSafetyAlert;
use App\Models\SafetyAlert;
use App\Models\User;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Acknowledge / resolve, defined once and used by both the queue row and the detail page.
 *
 * "Acknowledge" exists so a second staff member can see somebody already has this one — during a
 * live panic alert, two people phoning the same person is a worse outcome than a slower response.
 */
final class SettleAlertActions
{
    public static function acknowledge(): Action
    {
        return Action::make('acknowledge')
            ->label(__('admin.safety.acknowledge'))
            ->icon(LucideIcon::Eye)
            // Deliberately not the primary colour: acknowledging is "I have this", resolving is
            // "this person is safe". Two identical green buttons invite the wrong one.
            ->color('gray')
            ->visible(fn (SafetyAlert $record): bool => $record->status === 'open')
            ->action(fn (SafetyAlert $record) => self::settle($record, 'acknowledged'));
    }

    public static function resolve(): Action
    {
        return Action::make('resolve')
            ->label(__('admin.safety.resolve'))
            ->icon(LucideIcon::CircleCheck)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('admin.safety.resolve_description'))
            ->visible(fn (SafetyAlert $record): bool => $record->status !== 'resolved')
            ->action(fn (SafetyAlert $record) => self::settle($record, 'resolved'));
    }

    private static function settle(SafetyAlert $record, string $status): void
    {
        /** @var User $admin */
        $admin = Auth::user();
        app(ResolveSafetyAlert::class)->handle($record, $admin, $status);
        Notification::make()->title(__('admin.safety.'.$status.'_toast'))->success()->send();
    }
}
