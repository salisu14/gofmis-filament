@extends('pdf.layouts.official-document', [
    'documentTitle' => 'PROJECT PORTFOLIO REPORT',
    'subtitle' => 'Period: ' . \Carbon\Carbon::parse($startDate)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('d M Y')
])

@section('content')
    <table class="summary-grid">
        <tr>
            <td>
                <div class="lbl">Total Projects</div>
                <div class="num">{{ number_format($metrics['total_projects']) }}</div>
            </td>
            <td>
                <div class="lbl">Completed Projects</div>
                <div class="num" style="color: #047857;">{{ number_format($metrics['completed_projects']) }}</div>
            </td>
            <td>
                <div class="lbl">Active Projects</div>
                <div class="num" style="color: #D97706;">{{ number_format($metrics['active_projects']) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="lbl">Total Budget Allocated</div>
                <div class="num">NGN {{ number_format($metrics['total_budget'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Total Budget Spent</div>
                <div class="num" style="color: #BE123C;">NGN {{ number_format($metrics['total_spent'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Balance / Unspent</div>
                <div class="num" style="color: #0369A1; font-size: 14px;">NGN {{ number_format($metrics['total_budget'] - $metrics['total_spent'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 10%;">Date</th>
                <th style="width: 25%;">Project Name</th>
                <th style="width: 15%;">Zone</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 15%;" class="right">Allocated (NGN)</th>
                <th style="width: 15%;" class="right">Spent (NGN)</th>
                <th style="width: 6%;" class="center">%</th>
            </tr>
        </thead>
        <tbody>
            @forelse($projects as $index => $project)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $project->created_at?->format('d M Y') ?? 'N/A' }}</td>
                    <td><strong>{{ $project->name }}</strong></td>
                    <td>{{ $project->zone?->name ?? 'Global' }}</td>
                    <td>{{ $project->status?->label() ?? 'N/A' }}</td>
                    <td class="right"><strong>{{ number_format((float) $project->budget_allocated, 2) }}</strong></td>
                    <td class="right" style="color: #BE123C;">{{ number_format((float) $project->budget_spent, 2) }}</td>
                    <td class="center">{{ $project->progress_percentage }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center muted" style="padding: 16px;">No projects found for the selected filter parameters and period.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($projects) > 0)
            <tfoot>
                <tr>
                    <th colspan="5" class="right">Grand Total:</th>
                    <th class="right">NGN {{ number_format($metrics['total_budget'], 2) }}</th>
                    <th class="right" style="color: #BE123C;">NGN {{ number_format($metrics['total_spent'], 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
