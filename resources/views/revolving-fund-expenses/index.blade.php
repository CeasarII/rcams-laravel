@extends('layouts.app')

@section('title', 'Voyage Expenses')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Voyage Expenses</h1>
            <p class="page-subtitle">Revolving fund replenishment. A custodian prepares these from an open voyage on the fund's page.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('revolving-fund-expenses.index') }}" class="row g-2 align-items-center mb-3">
        <div class="col-auto"><label for="filter" class="col-form-label text-muted small">View</label></div>
        <div class="col-md-3 col-sm-5">
            <select name="filter" id="filter" class="form-select" onchange="this.form.submit()">
                @foreach ($filterOptions as $key => $option)
                    <option value="{{ $key }}" @selected($key === $filterKey)>{{ $option['label'] }} ({{ $option['count'] }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 col-sm-5">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search control no., fund, voyage…">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            @if ($search !== '')
                <a href="{{ route('revolving-fund-expenses.index', ['filter' => $filterKey]) }}" class="btn btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr><th>Control No.</th><th>Fund</th><th>Voyage</th><th>Prepared by</th><th class="text-end">Total</th><th>Status</th><th>Date</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="fw-semibold">{{ $expense->control_no }}</td>
                        <td>{{ $expense->voyage->revolvingFund->fund_code }}</td>
                        <td>{{ $expense->voyage->voyage_no }}</td>
                        <td>{{ $expense->preparer->full_name }}</td>
                        <td class="text-end">{{ number_format($expense->total_amount, 2) }}</td>
                        <td><x-status-badge :status="$expense->status" /></td>
                        <td>{{ $expense->expense_date->format('Y-m-d') }}</td>
                        <td class="text-end"><a href="{{ route('revolving-fund-expenses.show', $expense) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            @if ($search !== '')
                                Nothing matches &ldquo;{{ $search }}&rdquo; in this view.
                            @elseif ($filterKey === 'action')
                                Nothing needs your action right now.
                            @else
                                No voyage expenses in this view.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $expenses->links() }}</div>
@endsection
