<?php

namespace App\Http\Controllers;

use App\Models\WidowLoanWriteOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WidowLoanWriteOffController extends Controller
{
    /**
     * Download the supporting document for a loan write-off.
     */
    public function downloadDocument(Request $request, WidowLoanWriteOff $writeOff)
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanExportSensitiveData();

        if (! auth()->check()) {
            abort(403, 'Unauthorized.');
        }

        // Restrict document access to admin/super_admin roles
        if (! auth()->user()->hasAnyRole(['super_admin', 'admin'])) {
            abort(403, 'Unauthorized to view write-off documents.');
        }

        $path = $writeOff->write_off_document_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'Supporting document not found.');
        }

        return Storage::disk('local')->download($path);
    }
}
