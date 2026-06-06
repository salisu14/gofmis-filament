<?php
// app/Filament/Coordinator/Resources/LoanRequestResource.php

namespace App\Filament\Coordinator\Resources;

use App\Enums\WidowLoanStatus;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\CreateLoanRequest;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\EditLoanRequest;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ListLoanRequests;
use App\Filament\Coordinator\Resources\LoanRequestResource\Pages\ViewLoanRequest;
use App\Models\Widow;
use App\Models\WidowLoan;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoanRequestResource extends Resource
{
    protected static ?string $model = WidowLoan::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Loan Requests';
    protected static ?string $modelLabel = 'Loan Request';
    protected static ?string $pluralModelLabel = 'Loan Requests';
    protected static string|null|\UnitEnum $navigationGroup = 'Intervention Requests';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = auth()->user()?->coordinatedZone?->id;
        $isAdmin = auth()->user()?->hasRole(['admin', 'super_admin']);

        $query = parent::getEloquentQuery();

        if ($isAdmin || !$zoneId) {
            return $query;
        }

        return $query->whereHas('widow', fn(Builder $q) => $q->whereHas('deceased', fn($q2) =>
        $q2->where('zone_id', $zoneId)
        ));
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

        // Coordinators can only edit draft/pending loans they created
        return $record->status === WidowLoanStatus::DRAFT &&
            $record->widow?->deceased?->zone_id === $zoneId;
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

        return $schema
            ->schema([
                Section::make('Widow Selection')
                    ->schema([
                        Select::make('widow_id')
                            ->label('Widow')
                            ->relationship(
                                'widow',
                                'full_name',
                                fn(Builder $query) => $query
                                    ->whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
                                    ->where('is_eligible', true)
                                    ->where('is_married', false)
                                    ->whereDoesntHave('widowLoans', fn($q) => $q->whereNotIn('status', [
                                        WidowLoanStatus::COMPLETED->value,
                                        WidowLoanStatus::REJECTED->value,
                                    ]))
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (!$state) return;

                                $widow = Widow::find($state);
                                if ($widow && !$widow->canApplyForLoan()) {
                                    Notification::make()
                                        ->title('Not Eligible')
                                        ->body('This widow already has an active loan or is remarried.')
                                        ->warning()
                                        ->send();
                                    $set('widow_id', null);
                                }
                            })
                            ->helperText('Only eligible widows without active loans are shown'),
                    ]),

                Section::make('Loan Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('principal_amount')
                            ->label('Principal Amount (₦)')
                            ->numeric()
                            ->required()
                            ->prefix('₦')
                            ->minValue(1000)
                            ->step(1000),

                        TextInput::make('duration_months')
                            ->label('Duration (Months)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(24)
                            ->default(6),

                        Select::make('repayment_frequency')
                            ->label('Repayment Frequency')
                            ->options([
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->required()
                            ->default('weekly')
                            ->native(false),

                        TextInput::make('total_payable')
                            ->label('Total Payable (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->helperText('Auto-calculated with interest')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(0),
                    ]),

                Section::make('Purpose & Notes')
                    ->schema([
                        Textarea::make('purpose')
                            ->label('Loan Purpose')
                            ->required()
                            ->rows(3)
                            ->placeholder('Describe how the loan will be used...'),

                        Textarea::make('notes')
                            ->label('Additional Notes')
                            ->rows(2),
                    ]),

                Hidden::make('status')
                    ->default(WidowLoanStatus::DRAFT->value),

                Hidden::make('outstanding_balance')
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('widow.full_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('widow.deceased.zone.name')
                    ->label('Zone')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('principal_amount')
                    ->money('NGN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_months')
                    ->suffix(' months'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending',
                        'info' => 'approved',
                        'success' => 'disbursed',
                        'primary' => 'completed',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('total_paid')
                    ->money('NGN')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('fully_repaid')
                    ->boolean()
                    ->label('Fully Repaid'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'disbursed' => 'Disbursed',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\Filter::make('my_zone')
                    ->label('My Zone Only')
                    ->query(function (Builder $query) {
                        // ✅ FIXED: Use coordinatedZone instead of zone_id
                        $zoneId = auth()->user()?->coordinatedZone?->id;
                        if ($zoneId) {
                            $query->whereHas('widow.deceased', fn($q) => $q->where('zone_id', $zoneId));
                        }
                    })
                    ->default(),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn($record) => $record->status === WidowLoanStatus::DRAFT),

                Action::make('submit')
                    ->label('Submit for Approval')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Loan Request')
                    ->modalDescription('This will send the loan request for admin approval.')
                    ->visible(fn($record) => $record->status === WidowLoanStatus::DRAFT)
                    ->action(function (WidowLoan $record) {
                        $record->update(['status' => WidowLoanStatus::PENDING]);

                        Notification::make()
                            ->title('Submitted Successfully')
                            ->body('Loan request has been submitted for approval.')
                            ->success()
                            ->send();
                    }),

                Action::make('view_schedule')
                    ->label('Repayment Schedule')
                    ->icon('heroicon-m-calendar')
                    ->color('info')
                    ->url(fn($record) => static::getUrl('view', ['record' => $record]))
                    ->visible(fn($record) => in_array(($record->status->value ?? $record->status), ['approved', 'disbursed', 'completed'], true)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // No delete for coordinators
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoanRequests::route('/'),
            'create' => CreateLoanRequest::route('/create'),
            'edit' => EditLoanRequest::route('/{record}/edit'),
            'view' => ViewLoanRequest::route('/{record}'),
        ];
    }
}
