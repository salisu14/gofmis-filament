<?php
// app/Filament/Coordinator/Resources/WelfareRequestResource.php

namespace App\Filament\Coordinator\Resources;

use App\Enums\BeneficiaryStatus;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\CreateWelfareRequest;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\EditWelfareRequest;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\ListWelfareRequests;
use App\Filament\Coordinator\Resources\WelfareRequestResource\Pages\ViewWelfareRequest;
use App\Models\Deceased;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WelfareRequestResource extends Resource
{
    protected static ?string $model = WelfareBeneficiary::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'Welfare Requests';
    protected static ?string $modelLabel = 'Welfare Request';
    protected static ?string $pluralModelLabel = 'Welfare Requests';
    protected static string|null|\UnitEnum $navigationGroup = 'Intervention Requests';
    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = auth()->user()?->coordinatedZone?->id;
        $isAdmin = auth()->user()?->hasRole(['admin', 'super_admin']);

        $query = parent::getEloquentQuery();

        if ($isAdmin || !$zoneId) {
            return $query;
        }

        return $query->whereHas('deceased', fn(Builder $q) => $q->where('zone_id', $zoneId));
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super_admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if ($user->hasRole(['admin', 'super_admin'])) return true;

        // ✅ FIXED: Use coordinatedZone for zone comparison
        $zoneId = $user?->coordinatedZone?->id;

        return $record->status === BeneficiaryStatus::PENDING &&
            $record->deceased?->zone_id === $zoneId;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['admin', 'super_admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = $user?->coordinatedZone?->id;

        // Get active/open welfare packages
        $activePackages = WelfarePackage::active()->orWhere->open()->get();

        return $schema
            ->schema([
                Section::make('Package Selection')
                    ->schema([
                        Select::make('welfare_package_id')
                            ->label('Welfare Package')
                            ->options(fn() => WelfarePackage::whereIn('status', ['open', 'active'])
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (!$state) return;

                                $package = WelfarePackage::find($state);
                                if ($package) {
                                    $set('package_description', $package->description);
                                    $set('package_period', $package->start_date?->format('M d, Y') . ' - ' . $package->end_date?->format('M d, Y'));
                                }
                            }),

                        Placeholder::make('package_description')
                            ->label('Package Description')
                            ->content(fn($state) => $state ?? 'Select a package to see details'),

                        Placeholder::make('package_period')
                            ->label('Distribution Period')
                            ->content(fn($state) => $state ?? 'N/A'),
                    ]),

                Section::make('Beneficiary Family')
                    ->schema([
                        Select::make('deceased_id')
                            ->label('Family Head (Deceased)')
                            ->relationship(
                                'deceased',
                                'full_name',
                                fn(Builder $query) => $query->where('zone_id', $zoneId)
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (!$state) return;

                                $deceased = Deceased::find($state);
                                if ($deceased) {
                                    $set('family_info', "Orphans: {$deceased->orphans()->count()}, Widows: {$deceased->widows()->count()}");
                                }
                            }),

                        Placeholder::make('family_info')
                            ->label('Family Summary')
                            ->content(fn($state) => $state ?? 'Select a family to see summary'),
                    ]),

                Section::make('Request Details')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason/Justification')
                            ->required()
                            ->rows(3)
                            ->placeholder('Explain why this family needs welfare support...'),

                        KeyValue::make('additional_info')
                            ->label('Additional Information')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->helperText('Any extra details about the request'),
                    ]),

                Hidden::make('status')
                    ->default(BeneficiaryStatus::PENDING->value),

                Hidden::make('suggested_by')
                    ->default($user->id),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('welfarePackage.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('deceased.full_name')
                    ->label('Family Head')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('deceased.zone.name')
                    ->label('Zone')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'info' => 'collected',
                    ]),

                Tables\Columns\IconColumn::make('collection_status')
                    ->label('Collected')
                    ->boolean(),

                Tables\Columns\TextColumn::make('collected_at')
                    ->date('M d, Y')
                    ->placeholder('Not collected'),

                Tables\Columns\TextColumn::make('suggester.name')
                    ->label('Requested By')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'collected' => 'Collected',
                    ]),

                Tables\Filters\SelectFilter::make('welfare_package_id')
                    ->label('Package')
                    ->relationship('welfarePackage', 'name'),

                // ✅ FIXED: Use coordinatedZone instead of zone_id
                Tables\Filters\Filter::make('my_zone')
                    ->label('My Zone Only')
                    ->query(function (Builder $query) {
                        $zoneId = auth()->user()?->coordinatedZone?->id;
                        if ($zoneId) {
                            $query->whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId));
                        }
                    })
                    ->default(),

                Tables\Filters\Filter::make('not_collected')
                    ->label('Not Yet Collected')
                    ->query(fn($q) => $q->approved()->notCollected()),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn($record) => $record->status === BeneficiaryStatus::PENDING),

                Action::make('mark_collected')
                    ->label('Mark Collected')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Collection')
                    ->modalDescription('Mark this welfare item as collected?')
                    ->visible(fn($record) => $record->canBeCollected())
                    ->action(function (WelfareBeneficiary $record) {
                        $record->markAsCollected('Collected by beneficiary', auth()->id());

                        Notification::make()
                            ->title('Marked as Collected')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWelfareRequests::route('/'),
            'create' => CreateWelfareRequest::route('/create'),
            'edit' => EditWelfareRequest::route('/{record}/edit'),
            'view' => ViewWelfareRequest::route('/{record}'),
        ];
    }
}
