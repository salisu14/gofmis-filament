<?php

namespace App\Http\Middleware\Imprest;

use App\Models\ImprestFund;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFundCustodian
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // ✅ FIX: Handle guest users
        if (!$user) {
            return $next($request); // allow login page
        }

        // Super admin bypass
        if ($user->hasRole('super_admin') || $user->hasPermissionTo('imprest.manage_all')) {
            return $next($request);
        }

        $fundId = $request->route('fund') ?? $request->input('fund_id');

        if (!$fundId) {
            return $next($request);
        }

        $fund = ImprestFund::find($fundId);

        if (!$fund) {
            abort(404, 'Fund not found.');
        }

        if ($fund->custodian_id !== $user->id) {
            abort(403, 'You are not the custodian of this fund.');
        }

        return $next($request);
    }
}
