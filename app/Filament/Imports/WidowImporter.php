<?php

namespace App\Filament\Imports;

use App\Models\Widow;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class WidowImporter extends Importer
{
    protected static ?string $model = Widow::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reg_no')
                ->label('Widow Reg No')
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
            ImportColumn::make('skills')
                ->rules(['nullable', 'string']),
            ImportColumn::make('is_eligible')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('is_married')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('address')
                ->rules(['nullable', 'string']),
            ImportColumn::make('child_sequence')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1']),
            ImportColumn::make('married_at')
                ->rules(['nullable', 'date']),
            ImportColumn::make('divorced_at')
                ->rules(['nullable', 'date']),
        ];
    }

    public function resolveRecord(): ?Widow
    {
        $regNo = $this->data['reg_no'] ?? null;
        $nin = $this->data['nin'] ?? null;

        if ($regNo && Widow::where('reg_no', $regNo)->exists()) {
            throw new RowImportFailedException("Widow with Reg No '{$regNo}' already exists.");
        }

        if ($nin && Widow::where('nin', $nin)->exists()) {
            throw new RowImportFailedException("Widow with NIN '{$nin}' already exists.");
        }

        return new Widow;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your widow import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
