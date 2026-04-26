<?php

namespace App\Filament\Imprest\Pages;

use App\Filament\Imprest\Resources\ImprestReplenishmentResource;
use App\Filament\Imprest\Resources\ImprestTransactionResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = -2;

    public function getHeaderActions(): array
    {
        $user = Auth::user();
        $actions = [];

        // Use hasPermissionTo() - NOT hasPermission()
        if ($user->hasPermissionTo('imprest.transactions.create') || $user->hasPermissionTo('imprest.manage_all')) {
            $actions[] = Action::make('new_transaction')
                ->label('New Transaction')
                ->icon('heroicon-m-plus')
                ->url(ImprestTransactionResource::getUrl('create'))
                ->color('primary');
        }

        if ($user->hasPermissionTo('imprest.funds.replenish') || $user->hasPermissionTo('imprest.manage_all')) {
            $actions[] = Action::make('request_replenishment')
                ->label('Request Replenishment')
                ->icon('heroicon-m-arrow-path')
                ->url(ImprestReplenishmentResource::getUrl('create'))
                ->color('warning');
        }

        return $actions;
    }

//    public function getHeaderActions(): array
//    {
//        $user = Auth::user();
//        $actions = [];
//
//        if ($user->can('create', \App\Models\ImprestTransaction::class)) {
//            $actions[] = Action::make('new_transaction')
//                ->label('New Transaction')
//                ->icon('heroicon-m-plus')
//                ->url(\App\Filament\Imprest\Resources\ImprestTransactionResource::getUrl('create'))
//                ->color('primary');
//        }
//
//        if ($user->can('replenish', \App\Models\ImprestFund::class)) {
//            $actions[] = Action::make('request_replenishment')
//                ->label('Request Replenishment')
//                ->icon('heroicon-m-arrow-path')
//                ->url(\App\Filament\Imprest\Resources\ImprestReplenishmentResource::getUrl('create'))
//                ->color('warning');
//        }
//
//        return $actions;
//    }
}
