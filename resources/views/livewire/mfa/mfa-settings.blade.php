<div class="w-full max-w-4xl px-4 sm:px-6 lg:px-8">
    <div class="bg-slate-900/60 border border-slate-800 backdrop-blur-xl p-8 rounded-2xl shadow-2xl space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-6 border-b border-slate-800">
            <div>
                <h2 class="text-2xl font-bold text-slate-100">Account Security</h2>
                <p class="text-sm text-slate-400">Manage your Multi-Factor Authentication (MFA) setup and recovery codes.</p>
            </div>
            
            <a href="/admin" class="text-xs font-semibold text-slate-450 hover:text-slate-200 transition bg-slate-800/80 px-3.5 py-2 rounded-xl border border-slate-700/50">
                Back to Dashboard
            </a>
        </div>

        @if (session()->has('status'))
            <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-450 rounded-xl text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        @if (empty($action))
            <!-- Main Settings View -->
            <div class="space-y-8">
                <!-- Multi-Factor Authentication Section -->
                <div class="p-6 bg-slate-950/30 rounded-2xl border border-slate-800 space-y-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="space-y-1">
                            <h3 class="text-lg font-bold text-slate-100 uppercase tracking-wide">Multi-Factor Authentication</h3>
                            <p class="text-sm text-slate-400">Authenticator App</p>
                        </div>
                        <div>
                            @if (auth()->user()->twoFactorAuthEnabled())
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                    Enabled
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700/50">
                                    Disabled
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="text-sm text-slate-350">
                        @if (auth()->user()->twoFactorAuthEnabled())
                            <p>Your account is protected using an authenticator application.</p>
                            @if (auth()->user()->mfa_confirmed_at)
                                <p class="text-xs text-slate-500 mt-1.5">Confirmed: {{ auth()->user()->mfa_confirmed_at->format('M d, Y H:i:s') }}</p>
                            @endif
                        @else
                            <p>Configure an authenticator application to secure your account logins.</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-4 pt-2">
                        @if (auth()->user()->twoFactorAuthEnabled())
                            <button wire:click="selectAction('reconfigure')"
                                class="bg-slate-900 border border-slate-850 hover:border-slate-700 hover:bg-slate-850 text-slate-200 font-semibold py-2.5 px-4 rounded-xl text-sm transition">
                                Reconfigure Authenticator
                            </button>
                            
                            <button wire:click="selectAction('disable')"
                                {{ auth()->user()->isMfaRequired() ? 'disabled' : '' }}
                                class="bg-rose-950/20 border border-rose-900/30 hover:border-rose-900/60 text-rose-455 disabled:opacity-40 disabled:pointer-events-none font-semibold py-2.5 px-4 rounded-xl text-sm transition">
                                Disable MFA
                            </button>
                        @else
                            <a href="{{ route('mfa.enroll') }}"
                                class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold py-2.5 px-5 rounded-xl shadow-lg transition text-sm">
                                Enable Two-Factor Authentication
                            </a>
                        @endif
                    </div>

                    @if (auth()->user()->twoFactorAuthEnabled() && auth()->user()->isMfaRequired())
                        <p class="text-xs text-slate-500">
                            Note: Self-disabling is deactivated for your account because your role requires mandatory MFA.
                        </p>
                    @endif
                </div>

                <!-- Recovery Codes Section -->
                @if (auth()->user()->twoFactorAuthEnabled())
                    <div class="p-6 bg-slate-950/30 rounded-2xl border border-slate-800 space-y-6">
                        <div class="space-y-1">
                            <h3 class="text-lg font-bold text-slate-100 uppercase tracking-wide">Recovery Codes</h3>
                            <p class="text-xs text-slate-400">Active recovery codes: <strong class="text-slate-200">{{ count(auth()->user()->getAppAuthenticationRecoveryCodes() ?? []) }}</strong></p>
                        </div>

                        <p class="text-sm text-slate-350">
                            Recovery codes can be used if you lose access to your authenticator application.
                        </p>

                        <div class="pt-2">
                            <button wire:click="selectAction('regenerate')"
                                class="bg-slate-900 border border-slate-850 hover:border-slate-700 hover:bg-slate-850 text-slate-200 font-semibold py-2.5 px-4 rounded-xl text-sm transition">
                                Regenerate Recovery Codes
                            </button>
                        </div>
                    </div>
                @endif
            </div>

        @elseif ($action === 'disable')
            <!-- Action: Disable MFA -->
            <div class="bg-rose-955/10 border border-rose-900/30 p-6 rounded-2xl space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-rose-450">Disable Two-Factor Authentication</h3>
                    <p class="text-xs text-slate-400 mt-1">To disable MFA protection, please verify your credentials and confirm with the phrase.</p>
                </div>

                <form wire:submit.prevent="disableMfa" class="space-y-4">
                    <div x-data="{ show: false }">
                        <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Current Password</label>
                        <div class="relative">
                            <input wire:model.defer="password" :type="show ? 'text' : 'password'" id="password" placeholder="••••••••" autocomplete="current-password"
                                class="w-full bg-slate-950 border border-slate-850 rounded-xl py-2.5 pl-4 pr-10 text-slate-100 placeholder-slate-700 focus:outline-none focus:ring-2 focus:ring-rose-500/50 transition">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300">
                                <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-cloak>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 014.138-4.755M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l19 19"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-xs text-rose-500 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phrase" class="block text-sm font-medium text-slate-300 mb-1.5">
                            Type phrase: <strong class="text-rose-400 select-all font-mono">DISABLE MFA</strong>
                        </label>
                        <input wire:model.defer="phrase" type="text" id="phrase" autocomplete="off" placeholder="DISABLE MFA"
                            class="w-full bg-slate-950 border border-slate-850 rounded-xl py-2.5 px-4 text-slate-100 placeholder-slate-700 focus:outline-none focus:ring-2 focus:ring-rose-500/50 transition">
                        @error('phrase')
                            <p class="mt-1 text-xs text-rose-500 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="cancelAction" class="text-xs text-slate-400 hover:text-slate-200 transition font-medium px-4 py-2">
                            Cancel
                        </button>
                        <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition">
                            Disable MFA
                        </button>
                    </div>
                </form>
            </div>

        @elseif ($action === 'regenerate')
            <!-- Action: Regenerate Recovery Codes -->
            <div class="bg-slate-950/40 border border-slate-800 p-6 rounded-2xl space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-200">Regenerate Recovery Codes</h3>
                    <p class="text-xs text-slate-400 mt-1">This will invalidate all previous recovery codes. Confirm your current password to generate a new set.</p>
                </div>

                @if (empty($newRecoveryCodes))
                    <form wire:submit.prevent="regenerateRecoveryCodes" class="space-y-4">
                        <div x-data="{ show: false }">
                            <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Current Password</label>
                            <div class="relative">
                                <input wire:model.defer="password" :type="show ? 'text' : 'password'" id="password" placeholder="••••••••" autocomplete="current-password"
                                    class="w-full bg-slate-950 border border-slate-850 rounded-xl py-2.5 pl-4 pr-10 text-slate-100 placeholder-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50 transition">
                                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300">
                                    <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" x-cloak>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.024 10.024 0 014.138-4.755M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l19 19"/>
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-xs text-rose-500 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" wire:click="cancelAction" class="text-xs text-slate-400 hover:text-slate-200 transition font-medium px-4 py-2">
                                Cancel
                            </button>
                            <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-semibold py-2.5 px-4 rounded-xl text-sm transition">
                                Generate New Recovery Codes
                            </button>
                        </div>
                    </form>
                @else
                    <div class="space-y-6">
                        <x-security.recovery-codes :codes="$newRecoveryCodes" :email="auth()->user()->email" />
                        
                        <div class="flex justify-end pt-4 border-t border-slate-800/80">
                            <button type="button" wire:click="cancelAction" class="w-full sm:w-auto bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold py-3 px-6 rounded-xl shadow-lg transition">
                                I Have Saved My Recovery Codes
                            </button>
                        </div>
                    </div>
                @endif
            </div>

        @elseif ($action === 'reconfigure')
            <!-- Action: Reconfigure App -->
            <div class="bg-slate-950/45 border border-slate-800 p-6 rounded-2xl space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-200">Reconfigure Authenticator</h3>
                    <p class="text-xs text-slate-400 mt-1">Scan the new QR code below. Your old authenticator remains active until the new code is successfully verified.</p>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-8 p-4 bg-slate-900/40 rounded-2xl border border-slate-800">
                    <div class="bg-white p-2.5 rounded-lg shadow-inner flex items-center justify-center shrink-0">
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(140)->generate($provisioningUri) !!}
                    </div>
                    
                    <div class="space-y-2 max-w-xs text-left">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Manual Key</span>
                        <code class="block select-all bg-slate-950 border border-slate-850 text-amber-500 px-3 py-1.5 rounded-lg font-mono text-xs tracking-wider break-all">
                            {{ $newSecret }}
                        </code>
                    </div>
                </div>

                <form wire:submit.prevent="verifyReconfigure" class="space-y-4">
                    <div>
                        <label for="otp" class="block text-sm font-medium text-slate-300 mb-1.5">Enter Verification Code</label>
                        <input wire:model.defer="otp" type="text" id="otp" maxlength="6" autocomplete="off" placeholder="000000"
                            class="w-full text-center tracking-[0.5em] text-xl font-mono bg-slate-950 border border-slate-850 rounded-xl py-2.5 px-4 text-slate-100 placeholder-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50 transition">
                        @error('otp')
                            <p class="mt-1 text-xs text-rose-500 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="cancelAction" class="text-xs text-slate-400 hover:text-slate-200 transition font-medium px-4 py-2">
                            Cancel
                        </button>
                        <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-semibold py-2.5 px-4 rounded-xl text-sm transition">
                            Verify & Update
                        </button>
                    </div>
                </form>
            </div>
        @endif

    </div>
</div>
