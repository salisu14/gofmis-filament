<?php

namespace App\Filament\Imports;

use App\Models\Orphan;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class OrphanImporter extends Importer
{
    protected static ?string $model = Orphan::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reg_no')
                ->label('Orphan Reg No')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('nin')
                ->label('NIN')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('middle_name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('deceased')
                ->label('Deceased/Family Reg No')
                ->relationship(resolveUsing: 'reg_no')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('gender')
                ->requiredMapping()
                ->castStateUsing(fn (string $state): string => strtoupper(trim($state)))
                ->rules(['required', 'string', 'in:MALE,FEMALE']),
            ImportColumn::make('birth_date')
                ->label('Date of Birth')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('is_married')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('picture_url'),
        ];
    }

    public function resolveRecord(): ?Orphan
    {
        $regNo = $this->data['reg_no'] ?? null;
        $nin = $this->data['nin'] ?? null;

        if ($regNo && Orphan::where('reg_no', $regNo)->exists()) {
            throw new RowImportFailedException("Orphan with Reg No '{$regNo}' already exists.");
        }

        if ($nin && Orphan::where('nin', $nin)->exists()) {
            throw new RowImportFailedException("Orphan with NIN '{$nin}' already exists.");
        }

        return new Orphan;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your orphan import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
