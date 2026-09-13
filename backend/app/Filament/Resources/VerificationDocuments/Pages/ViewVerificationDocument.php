<?php

namespace App\Filament\Resources\VerificationDocuments\Pages;

use App\Domain\Verification\Actions\ReviewVerificationDocument;
use App\Domain\Verification\SignedDocumentUrl;
use App\Filament\Resources\VerificationDocuments\VerificationDocumentResource;
use App\Models\User;
use App\Models\VerificationDocument;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewVerificationDocument extends ViewRecord
{
    protected static string $resource = VerificationDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label('Open document')
                ->icon(LucideIcon::Eye)
                ->url(fn (VerificationDocument $record): string => app(SignedDocumentUrl::class)->for($record))
                // Purged bytes (retention, erasure) cannot be opened; the row stays for the audit.
                ->hidden(fn (VerificationDocument $record): bool => $record->purged_at !== null)
                ->openUrlInNewTab(),
            Action::make('approve')
                ->icon(LucideIcon::CircleCheck)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (VerificationDocument $record): bool => $record->status->value === 'pending')
                ->action(function (VerificationDocument $record): void {
                    /** @var User $reviewer */
                    $reviewer = Auth::user();
                    app(ReviewVerificationDocument::class)->approve($record, $reviewer);
                    Notification::make()->title('Document approved')->success()->send();
                }),
            Action::make('reject')
                ->icon(LucideIcon::CircleX)
                ->color('danger')
                ->schema([
                    Textarea::make('reason')->label('Reason')->required()->maxLength(2000),
                ])
                ->visible(fn (VerificationDocument $record): bool => $record->status->value === 'pending')
                ->action(function (VerificationDocument $record, array $data): void {
                    /** @var User $reviewer */
                    $reviewer = Auth::user();
                    app(ReviewVerificationDocument::class)->reject($record, $reviewer, (string) $data['reason']);
                    Notification::make()->title('Document rejected')->send();
                }),
        ];
    }
}
