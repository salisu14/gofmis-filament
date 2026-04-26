<?php

namespace App\Filament\Imprest\Tables\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class DateRangeFilter
{
    public static function make(string $name = 'date_range'): Filter
    {
        return Filter::make($name)
            ->schema([
                DatePicker::make('from')->native(false)->label('From'),
                DatePicker::make('until')->native(false)->label('To'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                    ->when($data['until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
            });
    }
}
