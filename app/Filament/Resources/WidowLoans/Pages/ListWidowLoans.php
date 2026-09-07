<?php

namespace App\Filament\Resources\WidowLoans\Pages;

use App\Filament\Exports\WidowLoanExporter;
use App\Filament\Resources\WidowLoans\WidowLoanResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListWidowLoans extends ListRecords
{
    protected static string $resource = WidowLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            ExportAction::make()->visible(fn () => ! auth()->user()?->isDemoObserver())
                ->exporter(WidowLoanExporter::class)
                ->enableVisibleTableColumnsByDefault(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\WidowLoanPortfolioOverviewWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => \Filament\Schemas\Components\Tabs\Tab::make('Active / Outstanding')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', \App\Enums\WidowLoanStatus::DISBURSED)->where('fully_repaid', false)->where('outstanding_balance', '>', 0))
                ->badge(\App\Models\WidowLoan::query()->where('status', \App\Enums\WidowLoanStatus::DISBURSED)->where('fully_repaid', false)->where('outstanding_balance', '>', 0)->count()),

            'draft_pending_approved' => \Filament\Schemas\Components\Tabs\Tab::make('Draft / Pending / Approved')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereIn('status', [\App\Enums\WidowLoanStatus::DRAFT, \App\Enums\WidowLoanStatus::PENDING, \App\Enums\WidowLoanStatus::APPROVED]))
                ->badge(\App\Models\WidowLoan::query()->whereIn('status', [\App\Enums\WidowLoanStatus::DRAFT, \App\Enums\WidowLoanStatus::PENDING, \App\Enums\WidowLoanStatus::APPROVED])->count()),

            'fully_repaid' => \Filament\Schemas\Components\Tabs\Tab::make('Fully Repaid')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where(fn ($q) => $q->where('fully_repaid', true)->orWhere('status', \App\Enums\WidowLoanStatus::COMPLETED)))
                ->badge(\App\Models\WidowLoan::query()->where(fn ($q) => $q->where('fully_repaid', true)->orWhere('status', \App\Enums\WidowLoanStatus::COMPLETED))->count()),

            'rejected' => \Filament\Schemas\Components\Tabs\Tab::make('Rejected')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('status', \App\Enums\WidowLoanStatus::REJECTED))
                ->badge(\App\Models\WidowLoan::query()->where('status', \App\Enums\WidowLoanStatus::REJECTED)->count()),

            'defaulted_written_off' => \Filament\Schemas\Components\Tabs\Tab::make('Defaulted / Written Off')
                ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where(fn ($q) => $q->whereIn('status', [\App\Enums\WidowLoanStatus::DEFAULTED, \App\Enums\WidowLoanStatus::WRITTEN_OFF])
                    ->orWhereIn('performance_status', [\App\Enums\WidowLoanPerformanceStatus::DEFAULTED, \App\Enums\WidowLoanPerformanceStatus::WRITTEN_OFF])))
                ->badge(\App\Models\WidowLoan::query()->where(fn ($q) => $q->whereIn('status', [\App\Enums\WidowLoanStatus::DEFAULTED, \App\Enums\WidowLoanStatus::WRITTEN_OFF])
                    ->orWhereIn('performance_status', [\App\Enums\WidowLoanPerformanceStatus::DEFAULTED, \App\Enums\WidowLoanPerformanceStatus::WRITTEN_OFF]))->count()),

            'all' => \Filament\Schemas\Components\Tabs\Tab::make('All Loans')
                ->badge(\App\Models\WidowLoan::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }
}
