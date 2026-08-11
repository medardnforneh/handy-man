<?php

namespace App\Filament\Resources\ReconciliationExceptions\Actions;

use App\Domain\Money\Actions\ResolveReconciliationException;
use App\Models\ReconciliationException;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Close an exception, recording who closed it and why.
 *
 * This deliberately resolves WITHOUT a ledger movement. The domain Action can post a balanced
 * adjustment, but choosing the accounts and directions for one is not a thing to do from a dropdown
 * in a queue — a mis-picked account produces a balanced transaction that is still wrong, and the
 * ledger is append-only, so the correction would need its own correction. Money-moving corrections
 * stay a deliberate, separate step, exactly as dispute adjudication already treats them.
 */
final class ResolveExceptionAction
{
    public static function make(): Action
    {
        return Action::make('resolve')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->modalHeading(__('admin.recon.resolve_heading'))
            ->modalDescription(__('admin.recon.resolve_description'))
            ->visible(fn (ReconciliationException $record): bool => $record->status === ReconciliationException::STATUS_OPEN)
            ->schema([
                Textarea::make('memo')
                    ->label(__('admin.recon.memo'))
                    ->helperText(__('admin.recon.memo_hint'))
                    ->required()
                    ->maxLength(2000),
            ])
            ->action(function (ReconciliationException $record, array $data): void {
                /** @var User $admin */
                $admin = Auth::user();

                app(ResolveReconciliationException::class)->handle($admin, $record, [], (string) $data['memo']);

                Notification::make()->title(__('admin.recon.resolved_toast'))->success()->send();
            });
    }
}
