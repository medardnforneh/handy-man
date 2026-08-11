<?php

namespace App\Filament\Resources\Reports\Actions;

use App\Domain\Safety\Actions\ReviewReport;
use App\Models\Report;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Close a report. Shared by the queue row and the detail page so both record the same decision the
 * same way — through the domain Action, which writes the attribution to the activity log.
 */
final class ReviewReportAction
{
    public static function make(): Action
    {
        return Action::make('review')
            ->label(__('admin.report.decide'))
            ->icon('heroicon-o-clipboard-document-check')
            ->visible(fn (Report $record): bool => ! in_array($record->status, ['resolved', 'dismissed'], true))
            ->schema([
                Select::make('decision')
                    ->label(__('admin.report.decision'))
                    ->options([
                        'reviewing' => __('admin.report.status.reviewing'),
                        'resolved' => __('admin.report.status.resolved'),
                        'dismissed' => __('admin.report.status.dismissed'),
                    ])
                    ->required(),
                Textarea::make('note')
                    ->label(__('admin.report.note'))
                    ->helperText(__('admin.report.note_hint'))
                    ->required()
                    ->maxLength(5000),
            ])
            ->action(function (Report $record, array $data): void {
                /** @var User $admin */
                $admin = Auth::user();

                app(ReviewReport::class)->handle(
                    $record,
                    $admin,
                    (string) $data['decision'],
                    (string) $data['note'],
                    Request::ip(),
                );

                Notification::make()->title(__('admin.report.recorded'))->success()->send();
            });
    }
}
