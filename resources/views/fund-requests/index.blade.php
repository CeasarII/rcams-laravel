@extends('layouts.app')

@section('title', 'Fund Requests')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Fund Requests</h1>
            <p class="page-subtitle">Cash advance and revolving fund requests.</p>
        </div>
        @can('create', \App\Models\FundRequest::class)
            <a href="{{ route('fund-requests.create') }}" class="btn btn-primary">New Fund Request</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('fund-requests.index') }}" class="row g-2 align-items-center mb-3">
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
                <a href="{{ route('fund-requests.index', ['filter' => $filterKey]) }}" class="btn btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Control No.</th>
                    <th>Requestor</th>
                    <th>Type</th>
                    <th>Vessel / Category</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($fundRequests as $fundRequest)
                    <tr>
                        <td>{{ $fundRequest->control_no }}</td>
                        <td>{{ $fundRequest->requestor->full_name }}</td>
                        <td>{{ $fundRequest->fundRequestType->label }}</td>
                        <td>{{ $fundRequest->vessel->name ?? $fundRequest->category }}</td>
                        <td class="text-end">{{ number_format($fundRequest->amount_requested, 2) }}</td>
                        <td><x-status-badge :status="$fundRequest->requestStatus" /></td>
                        <td>{{ $fundRequest->request_date->format('Y-m-d') }}</td>
                        <td class="text-end">
                            <a href="{{ route('fund-requests.show', $fundRequest) }}" class="btn btn-sm btn-outline-primary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            @if ($search !== '')
                                No requests match “{{ $search }}” in this view.
                            @elseif ($filterKey === 'action')
                                Nothing needs your action right now.
                            @else
                                No requests in this view.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $fundRequests->links() }}
    </div>
@endsection
