<?php

namespace App\Filament\Resources\Deceased\RelationManagers;

use App\Actions\Widow\RegisterWidowAction;
use App\Data\Widow\WidowData;
use App\Enums\IllnessCategory;
use App\Filament\Resources\Widows\Schemas\WidowForm;
use App\Models\Illness;
use App\Models\Medication;
use App\Models\Prescription;
use App\Models\Widow;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class WidowsRelationManager extends RelationManager
{
    protected static string $relationship = 'widows';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $title = 'Widows';

    protected static ?string $relatedRecordTitleAttribute = 'full_name';

    protected static string|null|\BackedEnum $icon = 'heroicon-o-heart';

    public function form(Schema $schema): Schema
    {
        return WidowForm::configure($schema, includeDeceased: false);
    }

    public function canCreate(): bool
    {
        if ($this->getRelationship()->count() >= 4) {
            return false;
        }

        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        $owner = $this->getOwnerRecord();

        return $user->isCoordinator()
            && $user->can('create_widows')
            && $user->managesZone($owner?->zone_id);
    }

    public function table(Table $table): Table
    {
        return $table
            ->groups([])
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo_url')
                    ->label('Profile Photo')
                    ->circular()
                    ->checkFileExistence(false)
                    ->defaultImageUrl(url('/images/placeholder-avatar.png')),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->state(fn ($record): string => (string) $record->display_name)
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('full_name', $direction))
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('nin')
                    ->label('NIN')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->searchable()
                    ->badge(),

                Tables\Columns\IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('skills')
                    ->label('Skills')
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Widow')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('4xl')
                    ->url(null)
                    ->visible(fn (RelationManager $livewire) => $livewire->canCreate())
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $data['deceased_id'] = $livewire->getOwnerRecord()->id;

                        return $data;
                    })
                    ->using(function (array $data, RelationManager $livewire): Widow {
                        $deceased = $livewire->getOwnerRecord();

                        $skills = $data['skills'] ?? [];
                        if (is_string($skills)) {
                            $skills = array_filter(array_map('trim', explode(',', $skills)));
                        }

                        $widowData = new WidowData(
                            deceasedId: $deceased->id,
                            firstName: $data['first_name'],
                            lastName: $data['last_name'],
                            middleName: $data['middle_name'] ?? null,
                            nin: $data['nin'] ?? null,
                            address: $data['address'] ?? null,
                            picture: $data['picture_url'] ?? null,
                            skills: is_array($skills) ? array_values($skills) : [],
                            isEligible: $data['is_eligible'] ?? true,
                            isMarried: $data['is_married'] ?? false,
                        );

                        return app(RegisterWidowAction::class)->execute($widowData);
                    }),
            ])
            ->recordActions([
                Action::make('manageMedical')
                    ->label('Medical')
                    ->icon('heroicon-m-beaker')
                    ->color('success')
                    ->modalHeading(fn (Widow $record) => "Medical History: {$record->full_name}")
                    ->modalWidth('5xl')
                    ->modalSubmitActionLabel('Save Updates')
                    ->fillForm(fn (Widow $record): array => [
                        'prescriptions' => $record->prescriptions()
                            ->with(['illnessModel', 'medications'])
                            ->latest('prescription_date')
                            ->get()
                            ->map(fn (Prescription $prescription): array => [
                                'id' => $prescription->id,
                                'doctor_name' => $prescription->doctor_name,
                                'illness_id' => $prescription->illness_id,
                                'prescription_date' => $prescription->prescription_date?->toDateString(),
                                'lab_test_cost' => $prescription->lab_test_cost,
                                'drug_cost' => $prescription->drug_cost,
                                'medications' => $prescription->medications->pluck('id')->all(),
                                'note' => $prescription->note,
                                'user_id' => $prescription->user_id ?? auth()->id(),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->schema([
                        Repeater::make('prescriptions')
                            ->defaultItems(0)
                            ->schema([
                                Hidden::make('id'),
                                Grid::make(3)->schema([
                                    TextInput::make('doctor_name')
                                        ->required()
                                        ->placeholder('Attending Doctor'),

                                    Select::make('illness_id')
                                        ->label('Diagnosis')
                                        ->options(fn (): array => Illness::query()
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all())
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->createOptionForm([
                                            TextInput::make('name')
                                                ->required()
                                                ->unique(Illness::class, 'name'),
                                            Select::make('category')
                                                ->options(IllnessCategory::class)
                                                ->enum(IllnessCategory::class)
                                                ->required()
                                                ->native(false),
                                            Textarea::make('description')->rows(2),
                                        ])
                                        ->createOptionUsing(fn (array $data): string => Illness::create($data)->getKey()),

                                    DatePicker::make('prescription_date')
                                        ->default(now())
                                        ->required()
                                        ->native(false),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('lab_test_cost')
                                        ->numeric()
                                        ->prefix('₦')
                                        ->default(0),
                                    TextInput::make('drug_cost')
                                        ->numeric()
                                        ->prefix('₦')
                                        ->default(0),
                                ]),
                                Select::make('medications')
                                    ->multiple()
                                    ->options(fn (): array => Medication::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->preload()
                                    ->searchable()
                                    ->hint('Search by drug name.'),
                                Textarea::make('note')
                                    ->label('Prescription Note')
                                    ->rows(2)
                                    ->placeholder('Dosage details or observations...')
                                    ->columnSpanFull(),
                                Hidden::make('user_id')
                                    ->default(auth()->id()),
                            ])
                            ->itemLabel(function (array $state): ?string {
                                $illnessName = ($state['illness_id'] ?? null)
                                    ? Illness::find($state['illness_id'])?->name
                                    : 'New Diagnosis';

                                $date = isset($state['prescription_date'])
                                    ? ' ('.date('d/m/Y', strtotime($state['prescription_date'])).')'
                                    : '';

                                return $illnessName.$date;
                            })
                            ->collapsible()
                            ->collapsed()
                            ->addActionLabel('New Medical Record'),
                    ])
                    ->action(function (Widow $record, array $data): void {
                        $submittedRows = collect($data['prescriptions'] ?? [])
                            ->filter(fn (array $row): bool => filled($row['doctor_name'] ?? null)
                                || filled($row['illness_id'] ?? null)
                                || filled($row['note'] ?? null));

                        $existingIds = $record->prescriptions()->pluck('id')->all();
                        $keptIds = [];

                        foreach ($submittedRows as $row) {
                            $illness = filled($row['illness_id'] ?? null)
                                ? Illness::find($row['illness_id'])
                                : null;

                            $prescription = filled($row['id'] ?? null)
                                ? $record->prescriptions()->whereKey($row['id'])->first()
                                : new Prescription;

                            if (! $prescription) {
                                continue;
                            }

                            $attributes = [
                                'doctor_name' => $row['doctor_name'] ?? null,
                                'illness_id' => $row['illness_id'] ?? null,
                                'prescription_date' => $row['prescription_date'] ?? now()->toDateString(),
                                'lab_test_cost' => $row['lab_test_cost'] ?? 0,
                                'drug_cost' => $row['drug_cost'] ?? 0,
                                'note' => $row['note'] ?? null,
                                'user_id' => $row['user_id'] ?? auth()->id(),
                            ];

                            if (DatabaseSchema::hasColumn('prescriptions', 'illness')) {
                                $attributes['illness'] = $illness?->name ?? 'Unspecified diagnosis';
                            }

                            $prescription->fill($attributes);

                            if (! $prescription->exists) {
                                $prescription->prescribable()->associate($record);
                            }

                            $prescription->save();
                            $prescription->medications()->sync($row['medications'] ?? []);

                            $keptIds[] = $prescription->id;
                        }

                        $idsToDelete = array_diff($existingIds, $keptIds);
                        if ($idsToDelete !== []) {
                            $record->prescriptions()->whereKey($idsToDelete)->get()->each(function (Prescription $prescription): void {
                                $prescription->medications()->detach();
                                $prescription->delete();
                            });
                        }

                        $record->touch();

                        Notification::make()
                            ->title('Medical records updated')
                            ->success()
                            ->send();
                    }),

                ViewAction::make(),
                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
