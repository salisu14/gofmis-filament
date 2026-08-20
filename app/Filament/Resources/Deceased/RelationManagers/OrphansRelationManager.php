<?php

namespace App\Filament\Resources\Deceased\RelationManagers;

use App\Actions\Orphan\RegisterOrphanAction;
use App\Data\Orphan\OrphanData;
use App\Enums\IllnessCategory;
use App\Enums\OrphanStatus;
use App\Filament\Resources\Orphans\OrphanResource;
use App\Filament\Resources\Orphans\Schemas\OrphanForm;
use App\Models\Illness;
use App\Models\Medication;
use App\Models\Orphan;
use App\Models\Prescription;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class OrphansRelationManager extends RelationManager
{
    protected static string $relationship = 'orphans';

    protected static ?string $relatedResource = OrphanResource::class;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $title = 'Orphans';

    protected static string|null|\BackedEnum $icon = 'heroicon-o-user-group';

    public function form(Schema $schema): Schema
    {
        return OrphanForm::configure($schema, includeDeceased: false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->groups([])
            ->columns([
                Tables\Columns\ImageColumn::make('picture_url')
                    ->label('Photo')
                    ->circular()
                    ->disk('public'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gender')
                    ->badge(),

                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->state(fn ($record) => $record->age ?? 'N/A'),

                Tables\Columns\TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (OrphanStatus $state): string => $state->label()
                    )
                    ->color(
                        fn (OrphanStatus $state): string => $state->color()
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Orphan')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('4xl')
                    ->url(null)
                    ->using(function (array $data, RelationManager $livewire): Orphan {
                        $deceased = $livewire->getOwnerRecord();

                        $orphanData = new OrphanData(
                            deceasedId: $deceased->id,
                            firstName: $data['first_name'],
                            lastName: $data['last_name'],
                            middleName: $data['middle_name'] ?? null,
                            gender: $data['gender'] instanceof \App\Enums\Gender ? $data['gender']->value : (string) $data['gender'],
                            birthDate: $data['birth_date'] instanceof \Carbon\Carbon ? $data['birth_date']->toDateString() : $data['birth_date'],
                            picture: $data['picture_url'] ?? null,
                            nin: $data['nin'] ?? null,
                            guardianName: $data['guardian_name'] ?? null,
                            guardianPhone: $data['guardian_phone'] ?? null,
                            address: $data['address'] ?? null,
                            hasBirthCert: $data['has_birth_cert'] ?? false,
                            birthCertificatePath: $data['birth_certificate_path'] ?? null,
                            educations: $data['educations'] ?? [],
                            vocationalSkills: $data['vocationalSkills'] ?? [],
                        );

                        return app(RegisterOrphanAction::class)->execute($orphanData);
                    }),
            ])
            ->recordActions([
                Action::make('manageMedical')
                    ->label('Medical')
                    ->icon('heroicon-m-beaker')
                    ->color('success')
                    ->modalHeading(fn (Orphan $record) => "Medical History: {$record->full_name}")
                    ->modalWidth('5xl')
                    ->modalSubmitActionLabel('Save Updates')
                    ->fillForm(fn (Orphan $record): array => [
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
                    ->action(function (Orphan $record, array $data): void {
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

                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
                ViewAction::make(),
            ]);
    }
}
