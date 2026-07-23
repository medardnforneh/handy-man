<?php

namespace App\Filament\Resources\VerificationDocuments;

use App\Domain\Verification\Actions\ReviewVerificationDocument;
use App\Filament\Resources\VerificationDocuments\Pages\ListVerificationDocuments;
use App\Filament\Resources\VerificationDocuments\Pages\ViewVerificationDocument;
use App\Filament\Resources\VerificationDocuments\Schemas\VerificationDocumentInfolist;
use App\Filament\Resources\VerificationDocuments\Tables\VerificationDocumentsTable;
use App\Models\VerificationDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The verification review queue (build plan P6-02, doc 04). Reviewers approve or reject documents;
 * approval raises the party's tier through the {@see ReviewVerificationDocument}
 * Action (never by editing a row). Opening a document goes through the signed-URL route, which logs
 * the view — reviewer throughput is the onboarding bottleneck, so the queue is built for speed.
 */
class VerificationDocumentResource extends Resource
{
    protected static ?string $model = VerificationDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Trust & safety';

    protected static ?string $recordTitleAttribute = 'id';

    public static function infolist(Schema $schema): Schema
    {
        return VerificationDocumentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VerificationDocumentsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = VerificationDocument::query()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
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
            'index' => ListVerificationDocuments::route('/'),
            'view' => ViewVerificationDocument::route('/{record}'),
        ];
    }
}
