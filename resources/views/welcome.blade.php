<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company->company_name ?? config('app.name', 'GOF MIS') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <style>
        :root {
            --bg-color: #f9fafb;
            --text-color: #111827;
            --text-muted: #4b5563;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #dbeafe;
            --success: #059669;
            --success-hover: #047857;
            --success-light: #d1fae5;
            --danger: #dc2626;
            --danger-hover: #b91c1c;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #111827;
                --text-color: #f9fafb;
                --text-muted: #9ca3af;
                --card-bg: #1f2937;
                --border-color: #374151;
                --primary: #3b82f6;
                --primary-light: rgba(59, 130, 246, 0.3);
                --success: #10b981;
                --success-light: rgba(16, 185, 129, 0.3);
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            width: 100%;
            max-width: 56rem;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .header {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .logo-img {
            margin: 0 auto;
            height: 6rem;
            width: auto;
            filter: drop-shadow(0 4px 3px rgba(0,0,0,0.07));
        }

        .fallback-logo {
            margin: 0 auto;
            height: 5rem;
            width: 5rem;
            background-color: var(--primary);
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            color: white;
        }

        .fallback-logo svg {
            width: 2.5rem;
            height: 2.5rem;
        }

        .title {
            font-size: 2.25rem;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .subtitle {
            font-size: 1.125rem;
            color: var(--text-muted);
            max-width: 42rem;
            margin: 0 auto;
        }

        .portals {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            max-width: 42rem;
            margin: 0 auto;
            width: 100%;
        }

        @media (min-width: 768px) {
            .portals {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .portal-card {
            background-color: var(--card-bg);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .portal-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            transform: translateY(-4px);
        }

        .portal-card.blue:hover { border-color: var(--primary); }
        .portal-card.green:hover { border-color: var(--success); }

        .portal-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .portal-icon {
            flex-shrink: 0;
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
        }

        .portal-card:hover .portal-icon {
            transform: scale(1.1);
        }

        .portal-card.blue .portal-icon {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .portal-card.green .portal-icon {
            background-color: var(--success-light);
            color: var(--success);
        }

        .portal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .portal-desc {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            min-height: 2.5rem;
            flex-grow: 1;
        }

        .btn {
            display: block;
            width: 100%;
            text-align: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .btn-primary.blue {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary.blue:hover { background-color: var(--primary-hover); }

        .btn-primary.green {
            background-color: var(--success);
            color: white;
        }

        .btn-primary.green:hover { background-color: var(--success-hover); }

        .btn-secondary {
            background-color: var(--card-bg);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        .btn-secondary:hover {
            background-color: var(--bg-color);
        }

        .footer {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            background-color: var(--card-bg);
            border-radius: 9999px;
            padding: 0.5rem 1.5rem 0.5rem 0.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
        }

        .user-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .user-info {
            text-align: left;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .user-email {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .divider {
            width: 1px;
            height: 2rem;
            background-color: var(--border-color);
        }

        .sign-out {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--danger);
            background: none;
            border: none;
            cursor: pointer;
        }

        .sign-out:hover {
            color: var(--danger-hover);
        }

        .copyright {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header / Branding -->
        <div class="header">
            @if($company->logoUrl())
                <img src="{{ $company->logoUrl() }}" alt="{{ $company->company_name }}" class="logo-img">
            @else
                <!-- Fallback Icon if no logo -->
                <div class="fallback-logo">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            @endif
            <h1 class="title">
                {{ $company->company_name ?? 'Garko Orphans Foundation' }}
            </h1>
            <p class="subtitle">
                Management Information System
            </p>
        </div>

        <!-- Portal Selection -->
        <div class="portals">
            @foreach($allPanels as $panel)
                @php
                    $isAccessible = $user ? $accessiblePanels->contains(fn($p) => $p->getId() === $panel->getId()) : true;
                    // If user is logged in and cannot access, we skip rendering or disable it.
                    if ($user && !$isAccessible) {
                        continue;
                    }

                    $panelName = $panel->getId() === 'admin' ? 'Administration' : str($panel->getId())->title();
                    $portalTitle = $panelName . ' Portal';

                    if ($panel->getId() === 'admin' && $user && $user->hasRole('demo_observer')) {
                        $portalTitle = 'Administration Portal — Read Only';
                    }

                    $loginUrl = url($panel->getPath() . '/login');
                    $dashboardUrl = url($panel->getPath());

                    // Specific panel details for better UI
                    $icon = 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'; // default home
                    $color = 'blue';
                    $description = "Access the {$panelName} portal";

                    if ($panel->getId() === 'admin') {
                        $icon = 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'; // shield
                        $description = 'System administration and comprehensive management.';
                    } elseif ($panel->getId() === 'coordinator') {
                        $icon = 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'; // users
                        $color = 'green';
                        $description = 'Zone coordination and beneficiary management.';
                    }
                @endphp

                <div class="portal-card {{ $color }}">
                    <div class="portal-header">
                        <div class="portal-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 1.5rem; height: 1.5rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="portal-title">{{ $portalTitle }}</h3>
                        </div>
                    </div>
                    <p class="portal-desc">
                        {{ $description }}
                    </p>

                    @if($user)
                        <a href="{{ $dashboardUrl }}" class="btn btn-primary {{ $color }}">
                            Open Portal
                        </a>
                    @else
                        <a href="{{ $loginUrl }}" class="btn btn-secondary">
                            Log in
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Auth Status & Footer -->
        <div class="footer">
            @if($user)
                <div class="user-badge">
                    <div class="user-avatar">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <div class="user-info">
                        <p class="user-name">{{ $user->name }}</p>
                        <p class="user-email">{{ $user->email }}</p>
                    </div>
                    <div class="divider"></div>

                    @php
                        $logoutUrl = $accessiblePanels->first() ? url($accessiblePanels->first()->getPath() . '/logout') : '#';
                    @endphp

                    <form method="POST" action="{{ $logoutUrl }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="sign-out">
                            Sign out
                        </button>
                    </form>
                </div>
            @else
                <p style="font-size: 0.875rem; color: var(--text-muted);">
                    Secure access restricted to authorized personnel.
                </p>
            @endif

            <p class="copyright">
                &copy; {{ date('Y') }} {{ $company->company_name ?? 'Garko Orphans Foundation' }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>
