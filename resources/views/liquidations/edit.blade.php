@extends('layouts.app')

@section('title', 'Edit '.$liquidation->control_no)

@section('content')
    <h1 class="h3 mb-4">Edit {{ $liquidation->control_no }}</h1>

    <form method="POST" action="{{ route('liquidations.update', $liquidation) }}" class="card p-4">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Cash Advance</label>
                <input type="text" class="form-control" value="{{ $liquidation->fundRequest->control_no }}" disabled>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="date_prepared">Date Prepared <span class="text-danger">*</span></label>
                <input type="date" id="date_prepared" name="date_prepared" required max="{{ now()->toDateString() }}"
                       class="form-control @error('date_prepared') is-invalid @enderror"
                       value="{{ old('date_prepared', $liquidation->date_prepared->format('Y-m-d')) }}">
                @error('date_prepared')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="purpose">Purpose</label>
                <textarea id="purpose" name="purpose" rows="3" class="form-control @error('purpose') is-invalid @enderror">{{ old('purpose', $liquidation->purpose) }}</textarea>
                @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="{{ route('liquidations.show', $liquidation) }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
@endsection
