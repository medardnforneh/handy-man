<?php

namespace App\Filament\Resources\Disputes\Actions;

use App\Domain\Disputes\Actions\AdjudicateDispute;
use App\Models\Dispute;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * One definition of "adjudicate", shared by the queue row action and the detail page header, so the
 * two can never drift into different decision paths.
 *
 * Money-moving adjudications (balanced adjustment txns) go through the domain Action's
 * adjustmentEntries; this UI records the decision + note. A ledger adjustment stays a deliberate,
 * separate step so a note can't accidentally move money.
 */
final class AdjudicateAction
{
    public static function make(): Action
    {
        return Action::make('adjudicate')
            ->icon('heroicon-o-scale')
            ->visible(fn (Dispute $record): bool => in_array($record->status, ['open', 'reviewing'], true))
            ->schema([
                Select::make('decision')
                    ->options(['resolved' => 'Resolve', 'rejected' => 'Reject'])
                    ->required(),
                Textarea::make('resolution_note')->label('Resolution note')->required()->maxLength(5000),
            ])
            ->action(function (Dispute $record, array $data): void {
                /** @var User $admin */
                $admin = Auth::user();
                $decision = (string) $data['decision'];

                app(AdjudicateDispute::class)->handle($record, $admin, $decision, (string) $data['resolution_note']);

                Notification::make()->title("Dispute {$decision}")->success()->send();
            });
    }
}
