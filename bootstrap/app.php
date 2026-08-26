<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('coordinator*')) {
                return route('filament.coordinator.auth.login');
            }

            if ($request->is('imprest*')) {
                return route('filament.imprest.auth.login');
            }

            if ($request->is('admin*')) {
                return route('filament.admin.auth.login');
            }

            $intended = session()->get('url.intended', '');
            if (str_contains($intended, '/coordinator')) {
                return route('filament.coordinator.auth.login');
            }
            if (str_contains($intended, '/imprest')) {
                return route('filament.imprest.auth.login');
            }
            if (str_contains($intended, '/admin')) {
                return route('filament.admin.auth.login');
            }

            $referer = $request->header('Referer', '');
            if (str_contains($referer, '/coordinator')) {
                return route('filament.coordinator.auth.login');
            }
            if (str_contains($referer, '/imprest')) {
                return route('filament.imprest.auth.login');
            }
            if (str_contains($referer, '/admin')) {
                return route('filament.admin.auth.login');
            }

            return route('filament.admin.auth.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
