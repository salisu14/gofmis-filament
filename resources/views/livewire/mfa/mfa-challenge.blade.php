<div class="w-full max-w-md">
    <div class="bg-slate-900/60 border border-slate-800 backdrop-blur-xl p-8 rounded-2xl shadow-2xl">
        <div class="text-center mb-8">
            <div class="mx-auto w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center mb-4 border border-amber-500/25">
                <svg class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-100">Two-Factor Authentication</h2>
            <p class="mt-2 text-sm text-slate-400">
                Please enter the 6-digit verification code from your authenticator app to access your account.
            </p>
        </div>

        <form wire:submit.prevent="verify" class="space-y-6">
            <div>
                <label for="code" class="block text-sm font-medium text-slate-300 mb-2">
                    Verification Code
                </label>
                <input wire:model.defer="code" type="text" id="code" maxlength="6" autocomplete="one-time-code" placeholder="000000"
                    class="w-full text-center tracking-[0.5em] text-2xl font-mono bg-slate-950 border border-slate-800 rounded-xl py-3 px-4 text-slate-100 placeholder-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
                @error('code')
                    <p class="mt-2 text-xs text-rose-500 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold py-3 px-4 rounded-xl shadow-lg shadow-amber-500/20 hover:shadow-amber-600/30 active:scale-[0.98] transition">
                Verify Code
            </button>
        </form>

        <div class="mt-6 text-center border-t border-slate-800/80 pt-6">
            <a href="{{ route('mfa.recovery') }}" class="text-sm font-medium text-amber-500 hover:text-amber-400 transition">
                Use a recovery code instead
            </a>
        </div>
    </div>
</div>
