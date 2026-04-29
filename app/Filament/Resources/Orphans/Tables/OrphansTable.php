<?php

namespace App\Filament\Resources\Orphans\Tables;

use App\Enums\Gender;
use App\Enums\VulnerabilityStatus;
use App\Filament\Resources\IdCards\IdCardResource;
use App\Filament\Resources\Orphans\Actions\GenerateIdCardAction;
use App\Models\Institution;
use App\Models\InterventionType;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use App\Models\Zone;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrphansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('picture_url')
                    ->label('Image')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->defaultImageUrl('https://via.placeholder.com/40'),
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
                TextColumn::make('gender')
                    ->badge()
                    ->sortable(),
                TextColumn::make('age')
                    ->label('Age')
                    ->state(fn($record) => $record->birth_date?->age)
                    ->sortable('birth_date')
                    ->alignCenter(),
                TextColumn::make('deceased.full_name')
                    ->label('Parent')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),

                // 2. Query by Gender
                SelectFilter::make('gender')
                    ->options(Gender::class),

                // 1. Query by Age (Range Filter)
                Filter::make('age_range')
                    ->schema([
                        TextInput::make('age_from')
                            ->label('Age From')
                            ->numeric(),
                        TextInput::make('age_to')
                            ->label('Age To')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['age_from'], fn($q) => $q->whereRaw('EXTRACT(YEAR FROM AGE(birth_date)) >= ?', [$data['age_from']]))
                            ->when($data['age_to'], fn($q) => $q->whereRaw('EXTRACT(YEAR FROM AGE(birth_date)) <= ?', [$data['age_to']]));
                    }),

                // 3. Query by School (Institution)
                SelectFilter::make('school_filter')
                    ->label('School')
                    ->options(fn() => Institution::pluck('name', 'id'))
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn(Builder $query, $value) => $query->whereHas('educations', fn($q) => $q->where('institution_id', $value))
                    ))
                    ->searchable()
                    ->preload(),

                // 4. Query by Class (Academic Level)
                SelectFilter::make('academic_level')
                    ->label('Class / Level')
                    ->options(fn() => OrphanEducation::query()
                        ->distinct()
                        ->pluck('level', 'level')
                        ->filter()
                        ->toArray())
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn(Builder $query, $value) => $query->whereHas('educations', fn($q) => $q->where('level', $value))
                    )),

                // 5. Query by Vulnerability (Linked to Deceased Parent)
                SelectFilter::make('vulnerability_filter')
                    ->label('Vulnerability')
                    ->options(VulnerabilityStatus::class)
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn(Builder $query, $value) => $query->whereHas('deceased', fn($q) => $q->where('vulnerability_status', $value))
                    )),

                // 6. Query by Zone (Linked to Deceased Parent)
                SelectFilter::make('zone_filter')
                    ->label('Zone')
                    ->options(fn() => Zone::pluck('name', 'id'))
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn(Builder $query, $value) => $query->whereHas('deceased', fn($q) => $q->where('zone_id', $value))
                    ))
                    ->searchable()
                    ->preload(),

                // 7. Query by Intervention Received
                SelectFilter::make('intervention_type_filter')
                    ->label('Support Received')
                    ->options(fn() => InterventionType::pluck('name', 'id'))
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn(Builder $query, $value) => $query->whereHas('interventions', fn($q) => $q->whereHas('request', fn($sq) => $sq->where('intervention_type_id', $value))
                        )
                    ))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'inactive' => 'Inactive',
                    ]),
            ], layout: FiltersLayout::Modal)
            ->deferFilters(false)
            ->filtersFormColumns(3)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // ID Card Actions
                    GenerateIdCardAction::make(),

                    Action::make('view_card')
                        ->label('View ID Card')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn(Orphan $record) => $record->idCards()->where('status', 'active')->first()
                            ? IdCardResource::getUrl('view', [
                                'record' => $record->idCards()->where('status', 'active')->first()
                            ])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn(Orphan $record): bool => $record->idCards()->where('status', 'active')->exists()
                        ),

                    Action::make('print_card')
                        ->label('Print Card')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn(Orphan $record) => ($card = $record->idCards()->where('status', 'active')->first())
                            ? route('id-cards.download', ['idCard' => $card])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn(Orphan $record): bool => $record->idCards()->where('status', 'active')->exists()
                        ),

                    DeleteAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
