<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\Staff\Support\StaffRoleField;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // "Grant access", not "New staff member": no account is created here. The person must
            // already have signed up and signed in with their own phone, which means the account
            // being promoted is provably theirs — a stronger guarantee than any password an admin
            // could have typed on their behalf.
            Action::make('grant')
                ->label(__('admin.staff.grant'))
                ->icon(LucideIcon::Plus)
                ->modalHeading(__('admin.staff.grant'))
                ->modalDescription(__('admin.staff.grant_hint'))
                ->modalSubmitActionLabel(__('admin.staff.grant_confirm'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('admin.staff.who'))
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => StaffRoleField::searchCandidates($search))
                        ->getOptionLabelUsing(fn (?string $value): ?string => StaffRoleField::labelFor($value))
                        ->searchPrompt(__('admin.staff.who_prompt'))
                        ->noSearchResultsMessage(__('admin.staff.who_none'))
                        ->helperText(__('admin.staff.who_hint')),

                    StaffRoleField::make(),
                ])
                ->action(fn (array $data) => StaffRoleField::grant(
                    (string) $data['user_id'],
                    $data['roles'] ?? [],
                )),
        ];
    }
}
