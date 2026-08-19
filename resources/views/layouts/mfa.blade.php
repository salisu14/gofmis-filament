<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Security Center - Garko Orphans Foundation' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.08), transparent 40%),
                        radial-gradient(circle at bottom left, rgba(30, 41, 59, 0.4), transparent 50%),
                        #020617;
        }
    </style>
    @livewireStyles
</head>
<body class="h-full flex flex-col justify-between">
    <header class="border-b border-slate-800/80 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-xl font-bold bg-gradient-to-r from-amber-400 to-amber-500 bg-clip-text text-transparent tracking-wide">
                    GOF MIS
                </span>
                <span class="h-4 w-px bg-slate-700"></span>
                <span class="text-xs font-semibold text-slate-400 tracking-wider uppercase">Security Center</span>
            </div>
            
            @auth
                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-400 hidden sm:inline-block">Logged in as: <strong class="text-slate-200">{{ auth()->user()->email }}</strong></span>
                    <form action="{{ route('mfa.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-amber-500 hover:text-amber-400 transition">
                            Sign Out
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-900 bg-slate-950/80 py-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} Garko Orphans Foundation (MIS) &bull; Secure Session
    </footer>

    @livewireScripts
</body>
</html>
