<?php

namespace App\Filament\Imprest\Widgets;

use App\Models\ImprestAuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Audit Activity';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImprestAuditLog::query()
                    ->with('user')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->default('System'),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Entity')
                    ->formatStateUsing(fn(string $state): string => class_basename($state)),

                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'success',
                        'approved' => 'primary',
                        'voided' => 'danger',
                        'custodian_transferred' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('ID'),
            ])
            ->paginated(false);
    }
}
