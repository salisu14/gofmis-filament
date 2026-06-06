<?php

namespace App\Filament\Coordinator\Widgets;

use App\Enums\BeneficiaryStatus;
use App\Enums\WidowLoanStatus;
use App\Models\InterventionRequest;
use App\Models\WelfareBeneficiary;
use App\Models\WidowLoan;
use Filament\Widgets\Widget;

class PendingItemsWidget extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['lg' => 2];

    protected string $view = 'filament.coordinator.widgets.pending-items';

    protected function getViewData(): array
    {
        $zoneId = auth()->user()?->coordinatedZone?->id;

        // ✅ Always return all keys with default values
        if (! $zoneId) {
            return [
                'counts' => [
                    'loans' => 0,
                    'education' => 0,
                    'healthcare' => 0,
                    'welfare' => 0,
                ],
                'items' => collect(),
            ];
        }

        // Counts — global scopes on Widow/Orphan auto-filter by zone
        $counts = [
            'loans' => WidowLoan::where('status', WidowLoanStatus::PENDING)->count(),
            'education' => InterventionRequest::where('status', 'pending')
                ->whereHas('type', fn ($q) => $q->where('name', 'like', '%education%'))
                ->count(),
            'healthcare' => \App\Models\Prescription::whereMonth('created_at', now()->month)->count(),
            'welfare' => WelfareBeneficiary::where('status', BeneficiaryStatus::PENDING)->count(),
        ];

        // Recent pending items
        $items = collect();

        WidowLoan::where('status', WidowLoanStatus::PENDING)
            ->with('widow')
            ->latest()
            ->limit(3)
            ->get()
            ->each(fn ($item) => $items->push([
                'type' => 'loan',
                'label' => 'Loan Request',
                'name' => $item->widow?->full_name ?? 'Unknown',
                'detail' => '₦'.number_format($item->principal_amount, 2),
                'status' => 'Pending Approval',
                'color' => 'warning',
                'icon' => 'heroicon-m-banknotes',
                'url' => \App\Filament\Coordinator\Resources\LoanRequestResource::getUrl('view', ['record' => $item]),
                'time' => $item->created_at,
            ]));

        InterventionRequest::where('status', 'pending')
            ->whereHas('type', fn ($q) => $q->where('name', 'like', '%education%'))
            ->with('orphan')
            ->latest()
            ->limit(3)
            ->get()
            ->each(fn ($item) => $items->push([
                'type' => 'education',
                'label' => 'Education Request',
                'name' => $item->orphan?->full_name ?? 'Unknown',
                'detail' => $item->type?->name ?? '',
                'status' => 'Pending',
                'color' => 'info',
                'icon' => 'heroicon-m-academic-cap',
                'url' => \App\Filament\Coordinator\Resources\EducationRequestResource::getUrl('view', ['record' => $item]),
                'time' => $item->created_at,
            ]));

        WelfareBeneficiary::where('status', BeneficiaryStatus::PENDING)
            ->with('deceased', 'welfarePackage')
            ->latest()
            ->limit(3)
            ->get()
            ->each(fn ($item) => $items->push([
                'type' => 'welfare',
                'label' => 'Welfare Request',
                'name' => $item->deceased?->full_name ?? 'Unknown',
                'detail' => $item->welfarePackage?->name ?? '',
                'status' => 'Pending',
                'color' => 'warning',
                'icon' => 'heroicon-m-gift',
                'url' => \App\Filament\Coordinator\Resources\WelfareRequestResource::getUrl('view', ['record' => $item]),
                'time' => $item->created_at,
            ]));

        return [
            'counts' => $counts,
            'items' => $items->sortByDesc('time')->take(8)->values(),
        ];
    }
}
