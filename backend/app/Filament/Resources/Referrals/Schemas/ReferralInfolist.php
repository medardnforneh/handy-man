<?php

namespace App\Filament\Resources\Referrals\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class ReferralInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('detail')
                ->view('filament.infolists.referral')
                ->columnSpanFull(),
        ]);
    }
}
