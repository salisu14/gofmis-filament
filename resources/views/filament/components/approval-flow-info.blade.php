@if($flow)
<div class="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Status</span>
            <p class="mt-1 text-sm font-semibold">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                    @class([
                        'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' => $flow->status === 'pending',
                        'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' => $flow->status === 'approved',
                        'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200' => $flow->status === 'rejected',
                    ])
                >
                    {{ ucfirst($flow->status) }}
                </span>
            </p>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Step Progress</span>
            <p class="mt-1 text-sm">{{ $flow->current_step }} of {{ $flow->total_steps }}</p>
        </div>
    </div>

    @if($currentStep)
    <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Step</span>
        <p class="mt-1 text-sm">
            <span class="font-semibold">Step {{ $currentStep->step_number }}:</span>
            {{ ucfirst($currentStep->role_required ?? 'Approver Review') }}
        </p>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            Status: <span class="font-medium">{{ ucfirst($currentStep->status) }}</span>
        </p>
    </div>
    @endif

    @if($flow->approvalSteps->isNotEmpty())
    <div class="border-t border-gray-200 pt-3 dark:border-gray-700">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Approval History</span>
        <div class="mt-2 space-y-2">
            @foreach($flow->approvalSteps as $step)
            <div class="flex items-start justify-between text-xs">
                <span>
                    <span class="inline-block rounded bg-gray-200 px-2 py-1 font-semibold dark:bg-gray-700">
                        Step {{ $step->step_number }}
                    </span>
                </span>
                <span @class([
                    'inline-block rounded px-2 py-1 text-xs font-medium' => true,
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-200' => $step->status === 'pending',
                    'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200' => $step->status === 'waiting',
                    'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-200' => $step->status === 'approved',
                    'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-200' => $step->status === 'rejected',
                ])>
                    {{ ucfirst($step->status) }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endif

