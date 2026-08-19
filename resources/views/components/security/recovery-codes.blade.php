@props(['codes', 'email'])

<div x-data="recoveryCodesManager"
     data-codes="{{ json_encode($codes) }}"
     data-email="{{ $email }}"
     data-timestamp="{{ now()->toDateTimeString() }}"
     class="space-y-6">
     
    <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl flex gap-3">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <h4 class="text-sm font-bold text-amber-400">Save your recovery codes</h4>
            <p class="text-xs text-slate-400 mt-0.5">Please save these codes now. They will not be shown again, and cannot be retrieved later.</p>
        </div>
    </div>
    
    <div class="p-6 bg-slate-950 border border-slate-800 rounded-2xl">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 font-mono text-sm tracking-wider select-all">
            @foreach ($codes as $index => $code)
                <div class="flex items-center bg-slate-900 border border-slate-800/80 rounded-xl px-4 py-3 hover:border-slate-700 transition">
                    <span class="text-xs text-slate-500 font-sans mr-3 w-5 shrink-0">{{ $index + 1 }}.</span>
                    <span class="font-bold text-slate-200 select-all tracking-widest">{{ $code }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @error('download')
        <div class="p-4 bg-rose-500/10 border border-rose-500/25 rounded-xl text-rose-500 text-xs font-semibold">
            {{ $message }}
        </div>
    @enderror

    <!-- Copy & Download controls -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <button type="button" @click="copyAll()"
            class="flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-100 font-semibold py-3 px-4 rounded-xl text-sm border border-slate-700/30 transition active:scale-[0.98]">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
            </svg>
            <span x-text="copySuccess ? 'Copied!' : 'Copy Recovery Codes'"></span>
        </button>
        
        <button type="button" 
            wire:click="downloadRecoveryCodes"
            wire:loading.attr="disabled"
            wire:target="downloadRecoveryCodes"
            class="flex items-center justify-center gap-2 bg-slate-850 hover:bg-slate-750 text-slate-200 font-semibold py-3 px-4 rounded-xl text-sm border border-slate-800 transition active:scale-[0.98] disabled:opacity-50">
            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" wire:loading.remove wire:target="downloadRecoveryCodes">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span wire:loading.remove wire:target="downloadRecoveryCodes">Download Recovery Codes</span>
            <span wire:loading wire:target="downloadRecoveryCodes" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-slate-400 animate-infinite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Preparing Download...
            </span>
        </button>
    </div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('recoveryCodesManager', () => ({
        codes: [],
        email: '',
        timestamp: '',
        copySuccess: false,
        init() {
            this.codes = JSON.parse(this.$el.dataset.codes || '[]');
            this.email = this.$el.dataset.email || '';
            this.timestamp = this.$el.dataset.timestamp || '';
        },
        copyAll() {
            let text = 'GOF MIS Recovery Codes\n' +
                       'User: ' + this.email + '\n' +
                       'Generated: ' + this.timestamp + '\n\n';
            this.codes.forEach((code, index) => {
                text += (index + 1) + '. ' + code + '\n';
            });
            text += '\nIMPORTANT:\n' +
                    'Each code can be used only once.\n' +
                    'Store these codes securely.';
                    
            navigator.clipboard.writeText(text).then(() => {
                this.copySuccess = true;
                setTimeout(() => this.copySuccess = false, 3000);
            }).catch(err => {
                console.error('Clipboard copy failed:', err);
            });
        }
    }));
});
</script>
@endonce
