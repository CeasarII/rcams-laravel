@extends('layouts.app')

@section('title', 'Liquidations')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Cash Advance Liquidations</h1>
            <p class="page-subtitle">Liquidations start from a disbursed Cash Advance — open the cash advance and choose Start Liquidation.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('liquidations.index') }}" class="row g-2 align-items-center mb-3">
        <div class="col-auto">
            <label for="filter" class="col-form-label text-muted small">View</label>
        </div>
        <div class="col-md-3 col-sm-5">
            <select name="filter" id="filter" class="form-select" onchange="this.form.submit()">
                @foreach ($filterOptions as $key => $option)
                    <option value="{{ $key }}" @selected($key === $filterKey)>{{ $option['label'] }} ({{ $option['count'] }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 col-sm-5">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search control no. or requestor…">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            @if ($search !== '')
                <a href="{{ route('liquidations.index', ['filter' => $filterKey]) }}" class="btn btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Liquidation</th><th>Cash Advance</th><th>Requestor</th><th>Vessel / Category</th>
                    <th class="text-end">Advance</th><th class="text-end">Liquidated</th><th>Status</th><th>Prepared</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($liquidations as $liquidation)
                    <tr>
                        <td class="fw-semibold">{{ $liquidation->control_no }}</td>
                        <td>{{ $liquidation->fundRequest->control_no }}</td>
                        <td>{{ $liquidation->preparer->full_name }}</td>
                        <td>{{ $liquidation->fundRequest->vessel?->name ?? $liquidation->fundRequest->category }}</td>
                        <td class="text-end">{{ number_format($liquidation->cash_advance_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($liquidation->liquidated_amount, 2) }}</td>
                        <td><x-status-badge :status="$liquidation->status" /></td>
                        <td>{{ $liquidation->date_prepared->format('Y-m-d') }}</td>
                        <td class="text-end"><a href="{{ route('liquidations.show', $liquidation) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            @if ($search !== '')
                                No liquidations match &ldquo;{{ $search }}&rdquo; in this view.
                            @elseif ($filterKey === 'action')
                                Nothing needs your action right now.
                            @else
                                No liquidations in this view.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $liquidations->links() }}</div>
@endsection
