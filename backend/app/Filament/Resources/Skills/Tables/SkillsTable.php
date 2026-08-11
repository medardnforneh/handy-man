<?php

namespace App\Filament\Resources\Skills\Tables;

use App\Filament\Resources\Skills\SkillResource;
use App\Models\Skill;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SkillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_fr')->label(__('admin.trade.name_fr'))->searchable()->sortable(),
                TextColumn::make('name_en')->label(__('admin.trade.name_en'))->searchable()->sortable(),
                TextColumn::make('parent.name_fr')->label(__('admin.trade.parent'))->placeholder('—')->sortable(),
                TextColumn::make('risk_tier')
                    ->label(__('admin.trade.risk_tier'))
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => __('admin.trade.tier.'.$state))
                    ->colors(['gray' => 1, 'warning' => 2, 'danger' => 3]),
                IconColumn::make('requires_license')->label(__('admin.trade.licence_short'))->boolean(),
                TextColumn::make('maintenance_interval_days')
                    ->label(__('admin.trade.maintenance_short'))
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : __('admin.trade.every_n_days', ['n' => $state])),
            ])
            ->filters([
                SelectFilter::make('risk_tier')->label(__('admin.trade.risk_tier'))->options([
                    1 => __('admin.trade.tier.1'),
                    2 => __('admin.trade.tier.2'),
                    3 => __('admin.trade.tier.3'),
                ]),
                TernaryFilter::make('requires_license')->label(__('admin.trade.requires_license')),
                Filter::make('categories_only')
                    ->label(__('admin.trade.categories_only'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('parent_id')),
            ])
            ->defaultSort('name_fr')
            ->recordUrl(fn (Skill $record): string => SkillResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
