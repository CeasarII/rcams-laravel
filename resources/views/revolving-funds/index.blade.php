@extends('layouts.app')

@section('title', 'Revolving Funds')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Revolving Funds</h1>
            <p class="page-subtitle">Fund limits, balances and custodians. Open a fund for its voyages and ledger.</p>
        </div>
        @can('create', \App\Models\RevolvingFund::class)
            <a href="{{ route('revolving-funds.create') }}" class="btn btn-primary">New Revolving Fund</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('revolving-funds.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search fund code or name…">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            @if ($search !== '')
                <a href="{{ route('revolving-funds.index') }}" class="btn btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Fund</th><th>Vessel / Department</th><th>Custodian</th>
                    <th class="text-end">Limit</th><th class="text-end">Balance</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($funds as $fund)
                    <tr>
                        <td><span class="fw-semibold">{{ $fund->fund_code }}</span><div class="small text-muted">{{ $fund->fund_name }}</div></td>
                        <td>{{ $fund->vessel?->name ?? 'Shore Base' }}<div class="small text-muted">{{ $fund->department->name }}</div></td>
                        <td>{{ $fund->custodian->full_name }}</td>
                        <td class="text-end">{{ number_format($fund->fund_limit, 2) }}</td>
                        <td class="text-end {{ $fund->current_balance < 0 ? 'text-danger' : '' }}">{{ number_format($fund->current_balance, 2) }}</td>
                        <td><x-status-badge :status="$fund->status" /></td>
                        <td class="text-end"><a href="{{ route('revolving-funds.show', $fund) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No revolving funds{{ $search !== '' ? ' match your search' : ' yet' }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $funds->links() }}</div>
@endsection
