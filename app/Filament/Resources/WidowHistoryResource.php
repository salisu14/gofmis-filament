<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WidowHistoryResource\Pages\ListWidowHistories;
use App\Filament\Resources\Widows\WidowResource;
use App\Models\Widow;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WidowHistoryResource extends Resource
{
    protected static ?string $model = Widow::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-clock';

    protected static string|null|\UnitEnum $navigationGroup = 'Deceased';

    protected static ?string $navigationLabel = 'Widow History';

    protected static ?string $modelLabel = 'Widow History';

    protected static ?string $pluralModelLabel = 'Widow History';

    protected static ?int $navigationSort = 40;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_widows');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->historical();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->searchable()
                    ->badge()
                    ->sortable(),

                TextColumn::make('nin')
                    ->label('NIN')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('deceased.full_name')
                    ->label('Deceased Household')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->badge('success')
                    ->sortable(),

                TextColumn::make('married_at')
                    ->label('Remarriage Date')
                    ->date('d M, Y')
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('divorced_at')
                    ->label('Divorce Date')
                    ->date('d M, Y')
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('lifecycle_status')
                    ->label('Status')
                    ->state(fn (Widow $record): string => $record->is_married ? 'Remarried (Historical)' : 'Active Operational')
                    ->badge()
                    ->color(fn (string $state): string => str_contains($state, 'Remarried') ? 'danger' : 'success'),

                IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean(),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Widow $record): string => WidowResource::getUrl('view', ['record' => $record])),

                \Filament\Actions\Action::make('reactivateAfterDivorce')
                    ->label('Reactivate After Divorce')
                    ->icon('heroicon-m-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Widow After Divorce')
                    ->modalDescription('This action should only be used when the later marriage ended in divorce. If the later husband died, do not reactivate this record; register/create the widow under the later deceased husband\'s household instead.')
                    ->modalSubmitActionLabel('Yes, Reactivate')
                    ->visible(fn (Widow $record) => (bool) $record->is_married)
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('divorced_at')
                            ->label('Divorce / Reactivation Date')
                            ->default(now())
                            ->maxDate(now())
                            ->required()
                            ->rule(function ($record) {
                                return function (string $attribute, $value, \Closure $fail) use ($record) {
                                    $date = \Illuminate\Support\Carbon::parse($value);

                                    if ($date->isFuture()) {
                                        $fail('Divorce / reactivation date cannot be in the future.');

                                        return;
                                    }

                                    if ($record->married_at && $date->lt(\Illuminate\Support\Carbon::parse($record->married_at))) {
                                        $fail('Divorce date cannot be earlier than the recorded remarriage date ('.\Illuminate\Support\Carbon::parse($record->married_at)->format('d M, Y').').');
                                    }
                                };
                            }),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Optional notes about the divorce/reactivation...')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->reactivateAfterDivorce(
                            notes: $data['notes'] ?? null,
                            divorcedAt: $data['divorced_at'] ?? null
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Widow Reactivated')
                            ->body("{$record->full_name} has been reactivated following divorce.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWidowHistories::route('/'),
        ];
    }
}
