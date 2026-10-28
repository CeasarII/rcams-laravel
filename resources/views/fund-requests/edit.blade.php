@extends('layouts.app')

@section('title', 'Edit ' . $fundRequest->control_no)

@section('content')
    <h1 class="h3 mb-4">Edit {{ $fundRequest->control_no }}</h1>

    <form method="POST" action="{{ route('fund-requests.update', $fundRequest) }}" class="card p-4">
        @csrf
        @method('PUT')
        @include('fund-requests._form', ['fundRequest' => $fundRequest])

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="{{ route('fund-requests.show', $fundRequest) }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
@endsection
