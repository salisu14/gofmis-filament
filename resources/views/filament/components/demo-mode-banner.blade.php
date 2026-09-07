@if(auth()->user()?->isDemoObserver())
<div class="bg-amber-500 dark:bg-amber-600 text-white font-medium text-xs py-1.5 px-4 text-center shadow-sm flex items-center justify-center border-b border-amber-600 dark:border-amber-700" role="alert">
    <span>Demo Mode — Read Only — Changes are disabled for this demonstration account.</span>
</div>
@endif
