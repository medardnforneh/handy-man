<?php

namespace App\Filament\Resources\Skills;

use App\Filament\Resources\Skills\Pages\CreateSkill;
use App\Filament\Resources\Skills\Pages\EditSkill;
use App\Filament\Resources\Skills\Pages\ListSkills;
use App\Filament\Resources\Skills\Schemas\SkillForm;
use App\Filament\Resources\Skills\Tables\SkillsTable;
use App\Models\Skill;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The skills taxonomy (P1-07). Unlike the marketplace resources this one IS fully editable: it is
 * reference data, not a record of something that happened. No state machine governs it and no money
 * hangs off a row, so a form is the right tool — the taxonomy otherwise only changed by editing a
 * seeder and re-running it.
 *
 * Two fields carry real weight and are labelled as such in the form: `risk_tier` feeds the
 * AcceptPaidJob gate (P6-03), so raising it can block providers from accepting work; and
 * `maintenance_interval_days` is what makes a trade emit maintenance_due follow-ups (P7-07).
 */
class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $recordTitleAttribute = 'name_fr';

    protected static ?int $navigationSort = 90;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof Skill) {
            return null;
        }

        return $record->name_fr.' · '.$record->name_en;
    }

    public static function form(Schema $schema): Schema
    {
        return SkillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SkillsTable::configure($table);
    }

    /**
     * Deleting a trade would orphan provider_skills and the jobs referencing it. Retiring a trade is
     * a data-migration decision, not a button.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSkills::route('/'),
            'create' => CreateSkill::route('/create'),
            'edit' => EditSkill::route('/{record}/edit'),
        ];
    }
}
