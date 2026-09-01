<?php

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalController extends Controller
{
    public function __invoke(Request $request, CompanyInformationService $companyService)
    {
        $company = $companyService->get();
        $allPanels = collect(Filament::getPanels())
            ->filter(fn ($panel) => $panel->getId() !== 'imprest')
            ->values();

        $user = Auth::user();

        $accessiblePanels = [];

        if ($user) {
            $accessiblePanels = $allPanels->filter(fn ($panel) => $user->canAccessPanel($panel));
        }

        return view('welcome', [
            'company' => $company,
            'allPanels' => $allPanels,
            'accessiblePanels' => $accessiblePanels,
            'user' => $user,
        ]);
    }
}
