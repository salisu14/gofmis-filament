<div class="w-full max-w-2xl px-4 sm:px-6 lg:px-8">
    <div class="bg-slate-900/60 border border-slate-800 backdrop-blur-xl p-8 rounded-2xl shadow-2xl space-y-6">
        
        @if ($step === 1)
            <!-- Step 1: Re-confirm Password -->
            <div class="text-center mb-8">
                <div class="mx-auto w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center mb-4 border border-amber-500/25">
                    <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-100">Enable Two-Factor Authentication</h2>
                <p class="mt-2 text-sm text-slate-400">
                    To configure MFA, please first confirm your password.
                </p>
            </div>

            <form wire:submit.prevent="verifyPassword" class="space-y-6">
                <div x-data="{ show: false }">
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-2">
                        Password
                    </label>
                    <div class="relative">
                        <input wire:model.defer="password" :type="show ? 'text' : 'password'" id="password" placeholder="••••••••" autocomplete="current-password"
                            class="w-full bg-slate-950 border border-slate-850 rounded-xl py-3 pl-4 pr-10 text-slate-100 placeholder-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
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
                        <p class="mt-2 text-xs text-rose-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold py-3 px-4 rounded-xl shadow-lg shadow-amber-500/20 hover:shadow-amber-600/30 active:scale-[0.98] transition">
                    Confirm Password
                </button>
            </form>

        @elseif ($step === 2)
            <!-- Step 2: Scan QR Code & Enter OTP -->
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-slate-100">Setup Authenticator App</h2>
                <p class="mt-2 text-sm text-slate-400">
                    Scan the QR code below using your authenticator app (such as Google Authenticator, Microsoft Authenticator, or 1Password).
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-8 my-8 p-6 bg-slate-955/50 rounded-xl border border-slate-800">
                <div class="bg-white p-3 rounded-lg shadow-inner flex items-center justify-center shrink-0">
                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(160)->generate($provisioningUri) !!}
                </div>
                
                <div class="space-y-3 max-w-xs text-left">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">Manual Entry Key</span>
                    <code class="block select-all bg-slate-900 border border-slate-800 text-amber-500 px-3 py-2 rounded-lg font-mono text-sm tracking-wider break-all">
                        {{ $pendingSecret }}
                    </code>
                    <p class="text-xs text-slate-400">
                        If you cannot scan the QR code, manually type or copy this key into your authenticator app.
                    </p>
                </div>
            </div>

            <form wire:submit.prevent="verifyOtp" class="space-y-6">
                <div>
                    <label for="otp" class="block text-sm font-medium text-slate-300 mb-2">
                        Verification Code
                    </label>
                    <input wire:model.defer="otp" type="text" id="otp" maxlength="6" autocomplete="off" placeholder="000000"
                        class="w-full text-center tracking-[0.5em] text-2xl font-mono bg-slate-950 border border-slate-850 rounded-xl py-3 px-4 text-slate-100 placeholder-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
                    @error('otp')
                        <p class="mt-2 text-xs text-rose-500 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold py-3 px-4 rounded-xl shadow-lg shadow-amber-500/20 hover:shadow-amber-600/30 active:scale-[0.98] transition">
                    Verify and Enable
                </button>
            </form>

        @elseif ($step === 3)
            <!-- Step 3: Show Recovery Codes -->
            <div class="space-y-6">
                <div class="text-center mb-6">
                    <div class="mx-auto w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center mb-4 border border-emerald-500/25">
                        <svg class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-100">Two-Factor Authentication Confirmed!</h2>
                    <p class="mt-2 text-sm text-slate-400">
                        Authenticator app configured successfully. Save your recovery codes below.
                    </p>
                </div>

                <x-security.recovery-codes :codes="$recoveryCodes" :email="auth()->user()->email" />

                <div class="space-y-4 pt-4 border-t border-slate-800/80">
                    <button wire:click="complete"
                        class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-amber-500/20 hover:shadow-amber-600/30 active:scale-[0.98] transition">
                        I Have Saved My Recovery Codes
                    </button>
                </div>
            </div>
        @endif

    </div>
</div>
