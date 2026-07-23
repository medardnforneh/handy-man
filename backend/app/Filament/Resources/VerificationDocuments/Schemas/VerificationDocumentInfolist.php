<?php

namespace App\Filament\Resources\VerificationDocuments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * Verification document detail for the reviewer. The document itself is opened via the signed-URL
 * header action (which logs the view) — never embedded at a permanent path.
 */
class VerificationDocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('kind')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('grants_tier')->label('Grants tier')->badge(),
            TextEntry::make('party.display_name')->label('Party'),
            TextEntry::make('sha256')->label('SHA-256')->copyable(),
            TextEntry::make('expires_at')->dateTime()->placeholder('—'),
            TextEntry::make('reviewed_at')->dateTime()->placeholder('Not reviewed'),
            TextEntry::make('reject_reason')->placeholder('—')->columnSpanFull(),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }
}
