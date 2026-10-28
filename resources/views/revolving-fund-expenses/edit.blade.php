@extends('layouts.app')

@section('title', 'Edit '.$expense->control_no)

@section('content')
    <h1 class="h3 mb-4">Edit {{ $expense->control_no }}</h1>

    <form method="POST" action="{{ route('revolving-fund-expenses.update', $expense) }}" class="card p-4">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Fund / Voyage</label>
                <input type="text" class="form-control" disabled value="{{ $expense->voyage->revolvingFund->fund_code }} &middot; voyage {{ $expense->voyage->voyage_no }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="expense_date">Expense Date <span class="text-danger">*</span></label>
                <input type="date" id="expense_date" name="expense_date" required max="{{ now()->toDateString() }}"
                       class="form-control @error('expense_date') is-invalid @enderror"
                       value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}">
                @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks', $expense->remarks) }}</textarea>
                @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="{{ route('revolving-fund-expenses.show', $expense) }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
@endsection
