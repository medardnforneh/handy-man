<?php

namespace App\Filament\Resources\Skills\Schemas;

use App\Models\Skill;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SkillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.trade.names'))
                ->description(__('admin.trade.names_hint'))
                ->columns(2)
                ->schema([
                    // Both languages are required: the taxonomy is the one dataset where a missing
                    // translation is visible to customers, and FTS searches the column matching the
                    // query's language (P1-07b) — a blank name_en makes the trade unfindable in
                    // English rather than merely untranslated.
                    TextInput::make('name_fr')
                        ->label(__('admin.trade.name_fr'))
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set): void {
                            if ($state === null || $state === '' || $get('slug')) {
                                return;
                            }

                            $set('slug', Str::slug($state));
                        }),
                    TextInput::make('name_en')
                        ->label(__('admin.trade.name_en'))
                        ->required()
                        ->maxLength(120),
                    TextInput::make('slug')
                        ->label(__('admin.trade.slug'))
                        ->helperText(__('admin.trade.slug_hint'))
                        ->required()
                        ->maxLength(140)
                        ->unique(ignoreRecord: true)
                        ->rule('regex:/^[a-z0-9-]+$/'),
                    Select::make('parent_id')
                        ->label(__('admin.trade.parent'))
                        ->helperText(__('admin.trade.parent_hint'))
                        ->options(fn (): array => Skill::query()
                            ->whereNull('parent_id')
                            ->orderBy('name_fr')
                            ->pluck('name_fr', 'id')
                            ->all())
                        ->searchable()
                        ->native(false),
                ]),

            Section::make(__('admin.trade.rules'))
                ->description(__('admin.trade.rules_hint'))
                ->columns(2)
                ->schema([
                    Select::make('risk_tier')
                        ->label(__('admin.trade.risk_tier'))
                        ->helperText(__('admin.trade.risk_tier_hint'))
                        ->options([
                            1 => __('admin.trade.tier.1'),
                            2 => __('admin.trade.tier.2'),
                            3 => __('admin.trade.tier.3'),
                        ])
                        ->default(1)
                        ->required()
                        ->native(false),
                    TextInput::make('maintenance_interval_days')
                        ->label(__('admin.trade.maintenance_interval'))
                        ->helperText(__('admin.trade.maintenance_interval_hint'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3650),
                    Toggle::make('requires_license')
                        ->label(__('admin.trade.requires_license'))
                        ->helperText(__('admin.trade.requires_license_hint')),
                    Toggle::make('is_leaf')
                        ->label(__('admin.trade.is_leaf'))
                        ->helperText(__('admin.trade.is_leaf_hint'))
                        ->default(true),
                ]),
        ]);
    }
}
