<?php
// app/Filament\Coordinator\Widgets\RecentActivityWidget.php (Revised)

namespace App\Filament\Coordinator\Widgets;

use App\Models\Deceased;
use App\Models\InterventionRequest;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\WelfareBeneficiary;
use App\Models\Widow;
use App\Models\WidowLoan;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class RecentActivityWidget extends Widget
{
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = ['lg' => 2];
    protected string $view = 'filament.coordinator.widgets.recent-activity';

    protected function getViewData(): array
    {
        $zoneId = auth()->user()?->zone_id;
        if (!$zoneId) {
            return ['activities' => collect()];
        }

        $activities = collect();

        // Deceased registrations
        Deceased::where('zone_id', $zoneId)
            ->latest()
            ->limit(5)
            ->get()
            ->each(fn($item) => $activities->push([
                'type' => 'deceased_registered',
                'label' => 'Deceased Registered',
                'description' => $item->full_name,
                'icon' => 'heroicon-m-user-minus',
                'color' => 'gray',
                'time' => $item->created_at,
                'url' => \App\Filament\Coordinator\Resources\DeceasedResource::getUrl('view', ['record' => $item]),
            ]));

        // Orphan registrations
        Orphan::whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
            ->with('deceased')
            ->latest()
            ->limit(5)
            ->get()
            ->each(fn($item) => $activities->push([
                'type' => 'orphan_registered',
                'label' => 'Orphan Registered',
                'description' => $item->full_name,
                'icon' => 'heroicon-m-users',
                'color' => 'info',
                'time' => $item->created_at,
                'url' => \App\Filament\Coordinator\Resources\OrphanResource::getUrl('view', ['record' => $item]),
            ]));

        // Widow registrations
        Widow::whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
            ->with('deceased')
            ->latest()
            ->limit(5)
            ->get()
            ->each(fn($item) => $activities->push([
                'type' => 'widow_registered',
                'label' => 'Widow Registered',
                'description' => $item->full_name,
                'icon' => 'heroicon-m-heart',
                'color' => 'warning',
                'time' => $item->created_at,
                'url' => \App\Filament\Coordinator\Resources\WidowResource::getUrl('view', ['record' => $item]),
            ]));

        // Loan requests
        WidowLoan::whereHas('widow.deceased', fn($q) => $q->where('zone_id', $zoneId))
            ->with('widow')
            ->latest()
            ->limit(5)
            ->get()
            ->each(fn($item) => $activities->push([
                'type' => 'loan_requested',
                'label' => 'Loan Requested',
                'description' => '₦' . number_format($item->principal_amount, 2) . ' - ' . ($item->widow?->full_name ?? 'Unknown'),
                'icon' => 'heroicon-m-banknotes',
                'color' => 'success',
                'time' => $item->created_at,
                'url' => \App\Filament\Coordinator\Resources\LoanRequestResource::getUrl('view', ['record' => $item]),
            ]));

        // Healthcare requests
        Prescription::where(function (Builder $q) use ($zoneId) {
            $q->whereHas('prescribable', function (Builder $q2) use ($zoneId) {
                $q2->where(function (Builder $q3) use ($zoneId) {
                    $q3->where('prescribable_type', \App\Models\Orphan::class)
                        ->whereHas('deceased', fn($q4) => $q4->where('zone_id', $zoneId));
                })->orWhere(function (Builder $q3) use ($zoneId) {
                    $q3->where('prescribable_type', \App\Models\Widow::class)
                        ->whereHas('deceased', fn($q4) => $q4->where('zone_id', $zoneId));
                });
            });
        })
            ->latest()
            ->limit(5)
            ->get()
            ->each(fn($item) => $activities->push([
                'type' => 'healthcare_requested',
                'label' => 'Healthcare Request',
                'description' => $item->illness . ' (₦' . number_format($item->total_cost, 2) . ')',
                'icon' => 'heroicon-m-heart',
                'color' => 'danger',
                'time' => $item->created_at,
                'url' => \App\Filament\Coordinator\Resources\HealthcareRequestResource::getUrl('view', ['record' => $item]),
            ]));

        // Education requests
        InterventionRequest::whereHas('orphan.deceased', fn($q) => $q->where('zone_id', $zoneId))
            ->whereHas('type', fn($q) => $q->where('name', 'like', '%education%'))
            ->with('orphan', 'type')
            ->latest()
            ->limit(5)
            ->get()
            ->each(fn($item) => $activities->push([
                'type' => 'education_requested',
                'label' => 'Education Request',
                'description' => ($item->orphan?->full_name ?? 'Unknown') . ' - ' . ($item->type?->name ?? ''),
                'icon' => 'heroicon-m-academic-cap',
                'color' => 'primary',
                'time' => $item->created_at,
                'url' => \App\Filament\Coordinator\Resources\EducationRequestResource::getUrl('view', ['record' => $item]),
            ]));

        // Welfare requests
        WelfareBeneficiary::whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
            ->with('deceased', 'welfarePackage')
            ->latest()
            ->limit(5)
            ->get()
            ->each(fn($item) => $activities->push([
                'type' => 'welfare_requested',
                'label' => 'Welfare Request',
                'description' => ($item->welfarePackage?->name ?? 'Unknown') . ' - ' . ($item->deceased?->full_name ?? 'Unknown'),
                'icon' => 'heroicon-m-gift',
                'color' => 'warning',
                'time' => $item->created_at,
                'url' => \App\Filament\Coordinator\Resources\WelfareRequestResource::getUrl('view', ['record' => $item]),
            ]));

        // Sort by time and take top 15
        $sortedActivities = $activities
            ->sortByDesc('time')
            ->take(15)
            ->values();

        return [
            'activities' => $sortedActivities,
        ];
    }
}
