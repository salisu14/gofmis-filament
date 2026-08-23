<?php

// app/Filament\Coordinator\Resources\WelfareRequestResource/Pages/ListWelfareRequests.php

namespace App\Filament\Coordinator\Resources\WelfareRequestResource\Pages;

use App\Filament\Coordinator\Resources\WelfareRequestResource;
use App\Models\Deceased;
use App\Models\WelfarePackage;
use App\Services\Welfare\WelfareNominationService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWelfareRequests extends ListRecords
{
    protected static string $resource = WelfareRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('nominate_beneficiaries')
                ->label('Nominate Beneficiaries')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->form([
                    Select::make('welfare_package_id')
                        ->label('Open Welfare Package / Campaign')
                        ->options(fn () => WelfarePackage::open()->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->native(false),

                    Select::make('deceased_ids')
                        ->label('Nominated Deceased Families')
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->options(function () {
                            $user = auth()->user();
                            $isCoordinator = ! $user->hasAnyRole(['admin', 'super_admin']);
                            $zoneId = $user->coordinatedZone?->id;

                            $query = Deceased::with(['widows', 'orphans', 'zone']);

                            if ($isCoordinator && $zoneId) {
                                $query->where('zone_id', $zoneId);
                            }

                            return $query->get()
                                ->filter(function ($d) {
                                    $hasWidow = $d->widows->contains(fn ($w) => $w->isOperationalBeneficiary() && $w->is_eligible);
                                    $hasOrphan = $d->orphans->contains(fn ($o) => $o->isOperationalBeneficiary() && $o->is_eligible);

                                    return $hasWidow || $hasOrphan;
                                })
                                ->mapWithKeys(function ($d) {
                                    $widowCount = $d->widows->filter(fn ($w) => $w->isOperationalBeneficiary() && $w->is_eligible)->count();
                                    $orphanCount = $d->orphans->filter(fn ($o) => $o->isOperationalBeneficiary() && $o->is_eligible)->count();
                                    $zoneName = $d->zone?->name ?? 'Unassigned Zone';

                                    return [
                                        $d->id => "{$d->display_name} ({$d->reg_no}) - {$zoneName} [{$widowCount} Widow(s), {$orphanCount} Orphan(s)]",
                                    ];
                                });
                        }),
                ])
                ->action(function (array $data) {
                    $service = app(WelfareNominationService::class);
                    $result = $service->nominate(
                        $data['welfare_package_id'],
                        $data['deceased_ids'],
                        auth()->user()
                    );

                    $nominated = $result['nominated_count'];
                    $duplicates = $result['duplicates_count'];
                    $ineligible = $result['ineligible_count'];

                    Notification::make()
                        ->title('Welfare Nomination Completed')
                        ->body("{$nominated} beneficiaries nominated. {$duplicates} duplicates skipped. {$ineligible} ineligible rejected.")
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make()
                ->label('New Welfare Request'),
        ];
    }
}
