<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.audit.when')),
                // Users have no `name` column — the display name lives on the party. Reading
                // `actor.name` renders every human action as "System", which is the one thing an
                // audit trail must never do.
                TextColumn::make('actor.party.display_name')
                    ->label(__('admin.audit.who'))
                    ->searchable()
                    ->placeholder(__('admin.audit.system')),
                TextColumn::make('action')->badge()->searchable()->label(__('admin.audit.action')),
                TextColumn::make('subject_type')
                    ->label(__('admin.audit.subject'))
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : class_basename($state))
                    ->description(fn (ActivityLog $record): ?string => $record->subject_id),
                TextColumn::make('ip_address')->label(__('admin.audit.ip'))->placeholder('—')->toggleable(),
                // Filament hands this column the raw column value, so the model's array cast has not
                // necessarily been applied — it arrives as a JSON string. Accept either.
                TextColumn::make('context')
                    ->label(__('admin.audit.context'))
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        $decoded = is_array($state) ? $state : json_decode((string) $state, true);

                        return is_array($decoded)
                            ? (json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—')
                            : (string) $state;
                    })
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('admin.audit.action'))
                    ->options(fn (): array => ActivityLog::query()
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
                // The reason this table exists: document reads by staff. One click to the control.
                Filter::make('document_views')
                    ->label(__('admin.audit.document_views'))
                    ->query(fn (Builder $query): Builder => $query->where('action', 'verification_document.viewed')),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
