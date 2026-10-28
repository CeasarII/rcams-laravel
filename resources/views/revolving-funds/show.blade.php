@extends('layouts.app')

@section('title', $fund->fund_code)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $fund->fund_code }} &mdash; {{ $fund->fund_name }}</h1>
            <x-status-badge :status="$fund->status" class="fs-6" />
        </div>
        @can('update', $fund)
            <a href="{{ route('revolving-funds.edit', $fund) }}" class="btn btn-outline-secondary">Edit Fund</a>
        @endcan
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Voyages</span>
                    @if ($openVoyage)
                        @can('manageVoyage', $fund)
                            <form method="POST" action="{{ route('revolving-fund-voyages.expenses.store', $openVoyage) }}" class="mb-0">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary">Prepare Voyage Expenses</button>
                            </form>
                        @endcan
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light">
                        <tr><th>Voyage</th><th>Start</th><th>End</th><th class="text-end">Beginning</th><th class="text-end">Ending</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                        @forelse ($fund->voyages as $voyage)
                            <tr>
                                <td class="fw-semibold">{{ $voyage->voyage_no }}</td>
                                <td>{{ $voyage->start_date->format('Y-m-d') }}</td>
                                <td>{{ $voyage->end_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="text-end">{{ number_format($voyage->beginning_balance, 2) }}</td>
                                <td class="text-end">{{ $voyage->ending_balance !== null ? number_format($voyage->ending_balance, 2) : '—' }}</td>
                                <td><x-status-badge :status="$voyage->status" /></td>
                                <td class="text-end">
                                    @if ($voyage->isOpen())
                                        @can('manageVoyage', $fund)
                                            <form method="POST" action="{{ route('revolving-funds.voyages.end', [$fund, $voyage]) }}" class="d-flex gap-1 justify-content-end mb-0">
                                                @csrf
                                                <input type="date" name="end_date" required class="form-control form-control-sm" style="width: 9.5rem"
                                                       min="{{ $voyage->start_date->toDateString() }}" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Close this voyage?')">End voyage</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No voyages yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if (! $openVoyage)
                    @can('manageVoyage', $fund)
                        <div class="card-body border-top">
                            <form method="POST" action="{{ route('revolving-funds.voyages.store', $fund) }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Voyage No.</label>
                                    <input type="text" name="voyage_no" required maxlength="30" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Start date</label>
                                    <input type="date" name="start_date" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-sm btn-primary">Start Voyage</button>
                                </div>
                                <div class="col-12 form-text">The beginning balance is the fund's current balance ({{ number_format($fund->current_balance, 2) }}).</div>
                            </form>
                        </div>
                    @endcan
                @endif
            </div>

            <div class="card mb-4">
                <div class="card-header">Voyage Expenses (replenishments)</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Control No.</th><th>Voyage</th><th>Prepared by</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="fw-semibold">{{ $expense->control_no }}</td>
                                <td>{{ $expense->voyage->voyage_no }}</td>
                                <td>{{ $expense->preparer->full_name }}</td>
                                <td class="text-end">{{ number_format($expense->total_amount, 2) }}</td>
                                <td><x-status-badge :status="$expense->status" /></td>
                                <td class="text-end"><a href="{{ route('revolving-fund-expenses.show', $expense) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">None yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Ledger <span class="text-muted small">(latest {{ $transactions->count() }})</span></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Reference</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                        <tbody>
                        @forelse ($transactions as $transaction)
                            @php $sign = $transaction->type->sign; @endphp
                            <tr>
                                <td class="text-nowrap">{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                <td>{{ $transaction->type->label }}</td>
                                <td>{{ $transaction->reference_no ?: '—' }}</td>
                                <td>
                                    {{ $transaction->description }}
                                    @if ($transaction->fundRequest)
                                        <a href="{{ route('fund-requests.show', $transaction->fundRequest) }}">{{ $transaction->fundRequest->control_no }}</a>
                                    @endif
                                    @if ($transaction->expense)
                                        <a href="{{ route('revolving-fund-expenses.show', $transaction->expense) }}">{{ $transaction->expense->control_no }}</a>
                                    @endif
                                </td>
                                <td class="text-end {{ $sign < 0 ? 'text-danger' : 'text-success' }}">{{ $sign < 0 ? '−' : '+' }}{{ number_format($transaction->amount, 2) }}</td>
                                <td class="text-end">{{ number_format($transaction->balance_after, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No transactions yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Balance</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">Fund limit</dt>
                        <dd class="col-6 text-end">{{ number_format($fund->fund_limit, 2) }}</dd>
                        <dt class="col-6">Current balance</dt>
                        <dd class="col-6 text-end fw-bold {{ $fund->current_balance < 0 ? 'text-danger' : '' }}">{{ number_format($fund->current_balance, 2) }}</dd>
                        <dt class="col-6">Room to fund</dt>
                        <dd class="col-6 text-end">{{ number_format($fund->headroom(), 2) }}</dd>
                    </dl>
                    <p class="text-muted small mt-3 mb-0">
                        The balance drops when a voyage expense report is approved and is restored when it is disbursed.
                        New funding comes from a Revolving Fund request for this fund.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Custodian</dt><dd class="col-sm-7">{{ $fund->custodian->full_name }}</dd>
                        <dt class="col-sm-5">Vessel</dt><dd class="col-sm-7">{{ $fund->vessel?->name ?? 'Shore Base' }}</dd>
                        <dt class="col-sm-5">Department</dt><dd class="col-sm-7">{{ $fund->department->name }}</dd>
                        <dt class="col-sm-5">Company</dt><dd class="col-sm-7">{{ $fund->company->name }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
