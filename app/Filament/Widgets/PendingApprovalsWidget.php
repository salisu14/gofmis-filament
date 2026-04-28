<?php

namespace App\Filament\Widgets;

use App\Models\ApprovalFlow;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingApprovalsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Approvals';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_approval_flows') ?? false;
    }

//    protected function getTableQuery()
//    {
//        $user = auth()->user();
//
//        // Find flows where the current step requires a role the user has
//        return ApprovalFlow::where('status', 'pending')
//            ->whereHas('steps', function ($query) use ($user) {
//                $query->whereColumn('step_number', 'approval_flows.current_step')
//                    ->where('status', 'pending')
//                    ->where(function ($q) use ($user) {
//                        // User must have the role required by the step
//                        // This assumes user roles can be checked via $user->hasRole()
//                        // If roles are strings, we can use a join or check permissions
//                        $q->whereIn('role_required', $user->getRoleNames());
//                    });
//            })
//            ->orderBy('created_at', 'desc')
//            ->limit(10);
//    }

    public function getTableRecordKey($record): string
    {
        return $record->id;
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('model_type')
                ->label('Type')
                ->formatStateUsing(fn($state) => str($state)->afterLast('\\')->singular())
                ->sortable(),
            TextColumn::make('current_step')
                ->label('Step')
                ->formatStateUsing(fn($state, $record) => "{$state} / {$record->total_steps}")
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->label('Status')
                ->colors([
                    'warning' => 'pending',
                ])
                ->sortable(),
            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make()
                ->url(fn(ApprovalFlow $record) => route('filament.admin.resources.approval-flows.index', ['activeTab' => 'details'])),
        ];
    }
}

