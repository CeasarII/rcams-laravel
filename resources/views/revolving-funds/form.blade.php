@extends('layouts.app')

@section('title', $fund ? 'Edit '.$fund->fund_code : 'New Revolving Fund')

@section('content')
    <h1 class="h3 mb-4">{{ $fund ? 'Edit '.$fund->fund_code : 'New Revolving Fund' }}</h1>

    <form method="POST" action="{{ $fund ? route('revolving-funds.update', $fund) : route('revolving-funds.store') }}" class="card p-4">
        @csrf
        @if ($fund) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="fund_code">Fund Code <span class="text-danger">*</span></label>
                <input type="text" id="fund_code" name="fund_code" required maxlength="30"
                       class="form-control @error('fund_code') is-invalid @enderror" value="{{ old('fund_code', $fund?->fund_code) }}">
                @error('fund_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-8">
                <label class="form-label" for="fund_name">Fund Name <span class="text-danger">*</span></label>
                <input type="text" id="fund_name" name="fund_name" required maxlength="150"
                       class="form-control @error('fund_name') is-invalid @enderror" value="{{ old('fund_name', $fund?->fund_name) }}">
                @error('fund_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="department_id">Department <span class="text-danger">*</span></label>
                <select id="department_id" name="department_id" required class="form-select @error('department_id') is-invalid @enderror">
                    <option value="">Select&hellip;</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id', $fund?->department_id) === (string) $department->id)>{{ $department->name }} ({{ $department->company->name }})</option>
                    @endforeach
                </select>
                @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Shore Base funds route to the OE assigned to this department.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="vessel_id">Vessel</label>
                <select id="vessel_id" name="vessel_id" class="form-select @error('vessel_id') is-invalid @enderror">
                    <option value="">None &mdash; Shore Base fund</option>
                    @foreach ($vessels as $vessel)
                        <option value="{{ $vessel->id }}" @selected((string) old('vessel_id', $fund?->vessel_id) === (string) $vessel->id)>{{ $vessel->name }}</option>
                    @endforeach
                </select>
                @error('vessel_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">A vessel fund routes to the OE assigned to that vessel.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="custodian_id">Custodian <span class="text-danger">*</span></label>
                <select id="custodian_id" name="custodian_id" required class="form-select @error('custodian_id') is-invalid @enderror">
                    <option value="">Select&hellip;</option>
                    @foreach ($custodians as $custodian)
                        <option value="{{ $custodian->id }}" @selected((string) old('custodian_id', $fund?->custodian_id) === (string) $custodian->id)>{{ $custodian->full_name }} ({{ $custodian->employee_no }})</option>
                    @endforeach
                </select>
                @error('custodian_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Holds the cash, requests funding, starts voyages and prepares the voyage expenses. Must hold the Requestor role.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="fund_limit">Fund Limit <span class="text-danger">*</span></label>
                <input type="number" id="fund_limit" name="fund_limit" required step="0.01" min="0.01"
                       class="form-control @error('fund_limit') is-invalid @enderror" value="{{ old('fund_limit', $fund?->fund_limit) }}">
                @error('fund_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if ($fund)
                <div class="col-md-3">
                    <label class="form-label" for="status_id">Status</label>
                    <select id="status_id" name="status_id" required class="form-select @error('status_id') is-invalid @enderror">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((string) old('status_id', $fund->status_id) === (string) $status->id)>{{ $status->label }}</option>
                        @endforeach
                    </select>
                    @error('status_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 text-muted small">
                    Current balance {{ number_format($fund->current_balance, 2) }} &mdash; it only changes through the ledger
                    (funding, approved voyage expenses, replenishments), never by editing the fund.
                </div>
            @else
                <div class="col-md-3">
                    <label class="form-label" for="opening_balance">Opening Balance</label>
                    <input type="number" id="opening_balance" name="opening_balance" step="0.01" min="0"
                           class="form-control @error('opening_balance') is-invalid @enderror" value="{{ old('opening_balance') }}">
                    @error('opening_balance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Optional; recorded as the first ledger entry.</div>
                </div>
            @endif
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">{{ $fund ? 'Save Changes' : 'Create Fund' }}</button>
            <a href="{{ $fund ? route('revolving-funds.show', $fund) : route('revolving-funds.index') }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
@endsection
