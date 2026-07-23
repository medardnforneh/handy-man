<?php

namespace App\Filament\Resources\Disputes\Tables;

use App\Domain\Disputes\Actions\AdjudicateDispute;
use App\Models\Dispute;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DisputesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')->badge()->searchable(),
                TextColumn::make('engagement_id')->label('Engagement')->limit(8)->copyable(),
                TextColumn::make('body')->limit(60)->wrap(),
                TextColumn::make('status')->badge()->colors([
                    'danger' => 'open', 'warning' => 'reviewing', 'success' => 'resolved', 'gray' => 'rejected',
                ]),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['open' => 'Open', 'reviewing' => 'Reviewing', 'resolved' => 'Resolved', 'rejected' => 'Rejected'])
                    ->default('open'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('adjudicate')
                    ->icon('heroicon-o-scale')
                    ->visible(fn (Dispute $record): bool => in_array($record->status, ['open', 'reviewing'], true))
                    ->schema([
                        Select::make('decision')
                            ->options(['resolved' => 'Resolve', 'rejected' => 'Reject'])
                            ->required(),
                        Textarea::make('resolution_note')->label('Resolution note')->required()->maxLength(5000),
                    ])
                    // Money-moving adjudications (balanced adjustment txns) go through the Action's
                    // adjustmentEntries; this UI records the decision + note. A ledger adjustment is a
                    // deliberate, separate step so a note can't accidentally move money.
                    ->action(fn (Dispute $record, array $data) => self::adjudicate($record, (string) $data['decision'], (string) $data['resolution_note'])),
            ]);
    }

    private static function adjudicate(Dispute $record, string $decision, string $note): void
    {
        /** @var User $admin */
        $admin = Auth::user();
        app(AdjudicateDispute::class)->handle($record, $admin, $decision, $note);
        Notification::make()->title("Dispute {$decision}")->success()->send();
    }
}
