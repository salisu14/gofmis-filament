<?php

namespace App\Filament\Coordinator\Widgets;

use App\Enums\BeneficiaryStatus;
use App\Models\InterventionRequest;
use App\Models\WelfareBeneficiary;
use Filament\Widgets\Widget;

class PendingItemsWidget extends Widget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['lg' => 1];

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

        // Counts — zone scoped
        $counts = [
            'education' => InterventionRequest::where('status', 'pending')
                ->whereHas('type', fn ($q) => $q->where('name', 'like', '%education%'))
                ->whereHas('orphan.deceased', fn ($q) => $q->where('zone_id', $zoneId))
                ->count(),
            'welfare' => WelfareBeneficiary::where('status', BeneficiaryStatus::PENDING)
                ->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId))
                ->count(),
        ];

        // Recent pending items
        $items = collect();

        InterventionRequest::where('status', 'pending')
            ->whereHas('type', fn ($q) => $q->where('name', 'like', '%education%'))
            ->whereHas('orphan.deceased', fn ($q) => $q->where('zone_id', $zoneId))
            ->with('orphan')
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn ($item) => $items->push([
                'type' => 'education',
                'label' => 'Education Request',
                'name' => $item->orphan?->display_name ?? 'Unknown',
                'detail' => $item->type?->name ?? '',
                'status' => 'Pending',
                'color' => 'info',
                'icon' => 'heroicon-m-academic-cap',
                'url' => \App\Filament\Coordinator\Resources\EducationRequestResource::getUrl('view', ['record' => $item]),
                'time' => $item->created_at,
            ]));

        WelfareBeneficiary::where('status', BeneficiaryStatus::PENDING)
            ->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId))
            ->with('deceased', 'welfarePackage')
            ->latest()
            ->limit(4)
            ->get()
            ->each(fn ($item) => $items->push([
                'type' => 'welfare',
                'label' => 'Welfare Nomination',
                'name' => $item->deceased?->display_name ?? 'Unknown',
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
