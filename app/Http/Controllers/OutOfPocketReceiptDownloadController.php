<?php

namespace App\Http\Controllers;

use App\Models\OutOfPocketExpenditure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OutOfPocketReceiptDownloadController extends Controller
{
    public function __invoke(Request $request, OutOfPocketExpenditure $record)
    {
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if (method_exists($user, 'isCoordinator') && $user->isCoordinator()) {
            abort(403);
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('coordinator')) {
            abort(403);
        }

        $canView = $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->can('out_of_pocket_expenditure.view')
            || (method_exists($user, 'isDemoObserver') && $user->isDemoObserver());

        if (! $canView) {
            abort(403);
        }

        if (! $record->receipt_path || ! Storage::disk('public')->exists($record->receipt_path)) {
            abort(404, 'Receipt evidence file not found.');
        }

        return Storage::disk('public')->download($record->receipt_path);
    }
}
