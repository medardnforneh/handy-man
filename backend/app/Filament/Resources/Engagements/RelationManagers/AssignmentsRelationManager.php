<?php

namespace App\Filament\Resources\Engagements\RelationManagers;

use App\Domain\Engagements\Actions\AssignWorker;
use App\Domain\Engagements\Actions\UnassignWorker;
use App\Domain\Engagements\AssignmentConflict;
use App\Domain\Engagements\AssignmentRole;
use App\Domain\Engagements\AssignmentStatus;
use App\Domain\Engagements\WorkerDoubleBooked;
use App\Domain\Engagements\WorkerNotInProviderOrg;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Manual (re)assignment for staff (build plan P2-10). Assign/remove route through the same
 * {@see AssignWorker}/{@see UnassignWorker} Actions the API uses, so the org boundary, one-lead, and
 * no-double-booking invariants all still hold — the admin panel is a UI over the domain, not a way
 * around it. Domain problems surface as notifications rather than raw errors.
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('worker.party.display_name')->label('Worker'),
                TextColumn::make('role')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('scheduled_from')->dateTime()->placeholder('—'),
                TextColumn::make('scheduled_to')->dateTime()->placeholder('—'),
            ])
            ->headerActions([
                Action::make('assign')
                    ->label('Assign worker')
                    ->schema([
                        Select::make('worker_user_id')
                            ->label('Worker')
                            ->options(fn () => $this->workerOptions())
                            ->searchable()
                            ->required(),
                        Select::make('role')
                            ->options(['lead' => 'Lead', 'helper' => 'Helper'])
                            ->default('helper')
                            ->required(),
                        DateTimePicker::make('scheduled_from')->seconds(false),
                        DateTimePicker::make('scheduled_to')->seconds(false)->after('scheduled_from'),
                    ])
                    ->action(fn (array $data) => $this->assign($data)),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Assignment $record): bool => $record->status !== AssignmentStatus::Removed)
                    ->action(fn (Assignment $record) => app(UnassignWorker::class)->handle($record)),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assign(array $data): void
    {
        /** @var Engagement $engagement */
        $engagement = $this->getOwnerRecord();
        /** @var User $actor */
        $actor = Auth::user();
        $worker = User::query()->findOrFail((string) $data['worker_user_id']);

        try {
            app(AssignWorker::class)->handle(
                $actor,
                $engagement,
                $worker,
                AssignmentRole::from((string) $data['role']),
                isset($data['scheduled_from']) ? CarbonImmutable::parse((string) $data['scheduled_from']) : null,
                isset($data['scheduled_to']) ? CarbonImmutable::parse((string) $data['scheduled_to']) : null,
            );
            Notification::make()->title('Worker assigned')->success()->send();
        } catch (WorkerNotInProviderOrg|AssignmentConflict|WorkerDoubleBooked $e) {
            Notification::make()->title($e->problemTitle())->danger()->send();
        }
    }

    /**
     * Workers eligible for this engagement's provider: active org members, or the individual provider.
     *
     * @return array<string, string>
     */
    private function workerOptions(): array
    {
        /** @var Engagement $engagement */
        $engagement = $this->getOwnerRecord();
        $providerParty = $engagement->provider_party_id;

        $org = Organization::query()->where('party_id', $providerParty)->first();

        if ($org !== null) {
            return Membership::query()
                ->where('organization_id', $org->id)
                ->where('status', 'active')
                ->with('user.party')
                ->get()
                ->mapWithKeys(fn (Membership $m): array => [
                    $m->user_id => $m->user->party->display_name,
                ])
                ->all();
        }

        $user = User::query()->with('party')->where('party_id', $providerParty)->first();

        return $user !== null ? [$user->id => $user->party->display_name] : [];
    }
}
