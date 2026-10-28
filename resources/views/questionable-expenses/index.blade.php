@extends('layouts.app')

@section('title', 'Questionable Expenses')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Questionable Expenses</h1>
            <p class="page-subtitle">Liquidation items flagged by the Approver/OE or Accounting Staff, and what happens to them next.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('questionable-expenses.index') }}" class="row g-2 align-items-center mb-3">
        <div class="col-auto"><label for="filter" class="col-form-label text-muted small">View</label></div>
        <div class="col-md-3 col-sm-5">
            <select name="filter" id="filter" class="form-select" onchange="this.form.submit()">
                @foreach ($filterOptions as $key => $option)
                    <option value="{{ $key }}" @selected($key === $filterKey)>{{ $option['label'] }} ({{ $option['count'] }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 col-sm-5">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search QE, source document or supplier…">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            @if ($search !== '')
                <a href="{{ route('questionable-expenses.index', ['filter' => $filterKey]) }}" class="btn btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>QE</th><th>Source</th><th>Requestor</th><th>Item</th>
                    <th class="text-end">Amount</th><th>Status</th><th>Tagged</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($questionableExpenses as $qe)
                    <tr>
                        <td class="fw-semibold">{{ $qe->control_no }}</td>
                        <td>{{ $qe->sourceControlNo() }}</td>
                        <td>{{ $qe->owner()->full_name }}</td>
                        <td>{{ $qe->sourceItem()->supplier_name }} &mdash; {{ $qe->sourceItem()->purpose }}</td>
                        <td class="text-end">{{ number_format($qe->sourceItem()->gross_amount, 2) }}</td>
                        <td><x-status-badge :status="$qe->status" /></td>
                        <td>{{ $qe->tagged_at->format('Y-m-d') }}</td>
                        <td class="text-end"><a href="{{ route('questionable-expenses.show', $qe) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            @if ($search !== '')
                                Nothing matches &ldquo;{{ $search }}&rdquo; in this view.
                            @elseif ($filterKey === 'action')
                                Nothing needs your action right now.
                            @else
                                No questionable expenses in this view.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $questionableExpenses->links() }}</div>
@endsection
