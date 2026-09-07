@extends('pdf.layouts.official-document', [
    'documentTitle' => 'CONSOLIDATED FINANCIAL TRANSACTIONS & EXPENDITURE REPORT',
    'subtitle' => 'GOF MIS Official Financial Statement'
])

@section('content')
    {{-- Active Filter Summary --}}
    <table class="info-grid" style="margin-bottom: 12px;">
        <tr>
            <td class="label">Report Mode</td>
            <td class="value"><strong>{{ $mode === 'expenditure_only' ? 'Expenditure Only' : 'All Financial Movements' }}</strong></td>
            <td class="label">Date Range</td>
            <td class="value">
                {{ !empty($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from'])->format('d M Y') : 'All Time' }}
                to
                {{ !empty($filters['date_to']) ? \Carbon\Carbon::parse($filters['date_to'])->format('d M Y') : 'Present' }}
            </td>
        </tr>
        <tr>
            <td class="label">Classification</td>
            <td class="value">{{ $filters['classification'] ?? 'All Classifications' }}</td>
            <td class="label">Bank Account</td>
            <td class="value">
                @if(!empty($filters['bank_account_id']))
                    @php $acct = \App\Models\BankAccount::find($filters['bank_account_id']); @endphp
                    {{ $acct ? $acct->account_name . ' (' . $acct->account_number . ')' : 'Selected ID: ' . $filters['bank_account_id'] }}
                @else
                    All Foundation Accounts
                @endif
            </td>
        </tr>
        @if(!empty($filters['type']) || !empty($filters['search']) || !empty($filters['amount_min']) || !empty($filters['amount_max']))
        <tr>
            <td class="label">Specific Type</td>
            <td class="value">{{ !empty($filters['type']) ? ucfirst(str_replace('_', ' ', $filters['type'])) : 'All Types' }}</td>
            <td class="label">Search / Amount</td>
            <td class="value">
                @if(!empty($filters['search'])) Ref/Desc: "{{ $filters['search'] }}" @endif
                @if(!empty($filters['amount_min'])) Min: NGN {{ number_format((float)$filters['amount_min'], 2) }} @endif
                @if(!empty($filters['amount_max'])) Max: NGN {{ number_format((float)$filters['amount_max'], 2) }} @endif
            </td>
        </tr>
        @endif
    </table>

    {{-- Executive KPI Summary Grid --}}
    <h3 style="margin-bottom: 6px; color: #065F46;">Executive KPI Summary</h3>
    <table class="summary-grid">
        <tr>
            <td>
                <div class="lbl">Total Expenditure</div>
                <div class="num" style="color: #DC2626;">NGN {{ number_format($kpis['total_expenditure'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Income / Receipts</div>
                <div class="num" style="color: #059669;">NGN {{ number_format($kpis['income_receipts'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Loan Disbursements</div>
                <div class="num" style="color: #D97706;">NGN {{ number_format($kpis['loan_disbursements'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Loan Repayments</div>
                <div class="num" style="color: #2563EB;">NGN {{ number_format($kpis['loan_repayments'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="lbl">Internal Transfers</div>
                <div class="num">NGN {{ number_format($kpis['internal_transfers'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Project Expenditure</div>
                <div class="num">NGN {{ number_format($kpis['project_expenditure'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Education Expenditure</div>
                <div class="num">NGN {{ number_format($kpis['education_expenditure'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Intervention Expenditure</div>
                <div class="num">NGN {{ number_format($kpis['intervention_expenditure'], 2) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="lbl">Out of Pocket Exp.</div>
                <div class="num" style="color: #D97706;">NGN {{ number_format($kpis['out_of_pocket_expenditure'] ?? 0, 2) }}</div>
            </td>
            <td>
                <div class="lbl">Historical Imprest (Legacy)</div>
                <div class="num" style="color: #6B7280;">NGN {{ number_format($kpis['historical_imprest_expenditure'], 2) }}</div>
            </td>
            <td>
                <div class="lbl">Non-Cash Welfare</div>
                <div class="num" style="color: #1D4ED8;">{{ number_format($kpis['non_cash_welfare_count']) }} items</div>
            </td>
            <td>
                <div class="lbl">Net Cash Movement</div>
                <div class="num" style="color: {{ $kpis['net_cash_movement'] >= 0 ? '#059669' : '#DC2626' }};">
                    NGN {{ number_format($kpis['net_cash_movement'], 2) }}
                </div>
            </td>
            <td>
                <div class="lbl">Transaction Count</div>
                <div class="num">{{ number_format($kpis['transaction_count']) }}</div>
            </td>
        </tr>
    </table>

    {{-- Detailed Financial Transactions Register --}}
    <h3 style="margin-top: 14px; margin-bottom: 6px; color: #065F46;">Detailed Financial Transactions Register</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 9%;">Date</th>
                <th style="width: 12%;">Reference</th>
                <th style="width: 14%;">Classification</th>
                <th style="width: 10%;">Type</th>
                <th style="width: 17%;">Description</th>
                <th style="width: 12%;">Bank Account</th>
                <th style="width: 11%; text-align: right;">Outflow (Debit)</th>
                <th style="width: 11%; text-align: right;">Inflow (Credit)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $index => $tx)
                @php
                    $classification = \App\Services\ConsolidatedFinancialReportService::classifyType($tx->type, $tx->isInternalTransfer());
                    $isDebit = $tx->isDebitType();
                    $isCredit = $tx->isCreditType();
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $tx->date ? $tx->date->format('d M Y') : 'N/A' }}</td>
                    <td><strong>{{ $tx->reference }}</strong></td>
                    <td style="font-size: 9px;">{{ $classification }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $tx->type)) }}</td>
                    <td style="font-size: 9px;">{{ Str::limit($tx->description, 50) }}</td>
                    <td>{{ $tx->bankAccount?->account_name ?? 'N/A' }}</td>
                    <td style="text-align: right; color: #DC2626;">
                        {{ $isDebit ? number_format((float) $tx->amount, 2) : '—' }}
                    </td>
                    <td style="text-align: right; color: #059669;">
                        {{ $isCredit ? number_format((float) $tx->amount, 2) : '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; color: #6B7280; padding: 16px;">
                        No financial transactions match the selected report parameters.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($transactions) > 0)
            <tfoot>
                <tr>
                    <th colspan="7" style="text-align: right;">Total Outflows / Outlets:</th>
                    <th style="text-align: right; color: #DC2626;">NGN {{ number_format($kpis['total_expenditure'], 2) }}</th>
                    <th style="text-align: right; color: #059669;">NGN {{ number_format($kpis['income_receipts'], 2) }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
