<?php

namespace App\Filament\Resources\VerificationDocuments\Tables;

use App\Domain\Verification\Actions\ReviewVerificationDocument;
use App\Domain\Verification\SignedDocumentUrl;
use App\Models\User;
use App\Models\VerificationDocument;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class VerificationDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->badge()->searchable(),
                TextColumn::make('party.display_name')->label('Party')->searchable(),
                TextColumn::make('grants_tier')->label('Tier')->badge(),
                TextColumn::make('status')->badge()->colors([
                    'warning' => 'pending', 'success' => 'approved', 'danger' => 'rejected', 'gray' => 'expired',
                ]),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('reviewed_at')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'expired' => 'Expired'])
                    ->default('pending'),
            ])
            ->defaultSort('created_at', 'asc') // oldest pending first — a fair review queue
            ->recordActions([
                ViewAction::make(),
                Action::make('open')
                    ->label('Open document')
                    ->icon('heroicon-o-eye')
                    // Signed short-TTL URL; opening it streams the file through the route that logs the view.
                    ->url(fn (VerificationDocument $record): string => app(SignedDocumentUrl::class)->for($record))
                    ->openUrlInNewTab(),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VerificationDocument $record): bool => $record->status->value === 'pending')
                    ->action(fn (VerificationDocument $record) => self::approve($record)),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('reason')->label('Reason')->required()->maxLength(2000),
                    ])
                    ->visible(fn (VerificationDocument $record): bool => $record->status->value === 'pending')
                    ->action(fn (VerificationDocument $record, array $data) => self::reject($record, (string) $data['reason'])),
            ]);
    }

    private static function approve(VerificationDocument $record): void
    {
        /** @var User $reviewer */
        $reviewer = Auth::user();
        app(ReviewVerificationDocument::class)->approve($record, $reviewer);
        Notification::make()->title('Document approved')->success()->send();
    }

    private static function reject(VerificationDocument $record, string $reason): void
    {
        /** @var User $reviewer */
        $reviewer = Auth::user();
        app(ReviewVerificationDocument::class)->reject($record, $reviewer, $reason);
        Notification::make()->title('Document rejected')->send();
    }
}
