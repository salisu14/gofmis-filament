<?php
// app/Filament/Widgets/PendingApprovalsWidget.php

namespace App\Filament\Widgets;

use App\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use App\Models\ApprovalFlow;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingApprovalsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Approvals';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_approval_flows') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->recordActions($this->getTableActions());
    }

    protected function getTableQuery(): Builder
    {
        return ApprovalFlow::query()
            ->where('status', 'pending')
            ->latest();
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
            Action::make('view')
                ->label('View')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->url(fn(ApprovalFlow $record) => ApprovalFlowResource::getUrl('index', [
                    'activeTab' => 'details',
                    'record' => $record->id,
                ])),
        ];
    }
}
