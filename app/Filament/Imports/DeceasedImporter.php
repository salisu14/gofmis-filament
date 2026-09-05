<?php

namespace App\Filament\Imports;

use App\Models\Deceased;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class DeceasedImporter extends Importer
{
    protected static ?string $model = Deceased::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('reg_no')
                ->label('Deceased/Family Reg No')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('nin')
                ->label('NIN')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('middle_name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('number_of_orphans_left')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer', 'min:0']),
            ImportColumn::make('number_of_widows_left')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer', 'min:0']),
            ImportColumn::make('age')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('guardian_name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('guardian_phone')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('deceased_age')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('address')
                ->rules(['nullable', 'string']),
            ImportColumn::make('vulnerability_status')
                ->requiredMapping()
                ->castStateUsing(fn (string $state): string => static::normalizeVulnerabilityStatus($state))
                ->rules(['required', 'string', 'in:A,B,C']),
            ImportColumn::make('date_registered')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('death_cause')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('death_place')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('occupation')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('has_death_cert')
                ->boolean()
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('zone')
                ->relationship(resolveUsing: 'name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('date_of_birth')
                ->rules(['nullable', 'date']),
            ImportColumn::make('date_of_death')
                ->rules(['nullable', 'date']),
        ];
    }

    /**
     * Normalise a vulnerability-status value from a CSV to the canonical
     * VulnerabilityStatus enum backing value (A/B/C). Accepts the enum's own
     * backing values directly and maps its display labels, so imports stay
     * aligned with the authoritative model enum.
     */
    protected static function normalizeVulnerabilityStatus(string $state): string
    {
        $value = trim($state);

        if (\App\Enums\VulnerabilityStatus::tryFrom($value) !== null) {
            return $value;
        }

        return match (true) {
            str_contains($value, 'Critical') => \App\Enums\VulnerabilityStatus::A->value,
            str_contains($value, 'High') => \App\Enums\VulnerabilityStatus::B->value,
            str_contains($value, 'Moderate') => \App\Enums\VulnerabilityStatus::C->value,
            default => $value,
        };
    }

    public function resolveRecord(): ?Deceased
    {
        $regNo = $this->data['reg_no'] ?? null;
        $nin = $this->data['nin'] ?? null;

        if ($regNo && Deceased::where('reg_no', $regNo)->exists()) {
            throw new RowImportFailedException("Deceased with Reg No '{$regNo}' already exists.");
        }

        if ($nin && Deceased::where('nin', $nin)->exists()) {
            throw new RowImportFailedException("Deceased with NIN '{$nin}' already exists.");
        }

        return new Deceased;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your deceased import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
