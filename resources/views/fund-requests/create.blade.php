@extends('layouts.app')

@section('title', 'New Fund Request')

@section('content')
    <h1 class="h3 mb-4">New Fund Request</h1>

    <form method="POST" action="{{ route('fund-requests.store') }}" class="card p-4">
        @csrf
        @include('fund-requests._form', ['fundRequest' => null])

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save as Draft</button>
            <a href="{{ route('fund-requests.index') }}" class="btn btn-link">Cancel</a>
        </div>
        <div class="form-text mt-2">
            Saved as Draft first — submit it for OE validation from the request page.
        </div>
    </form>
@endsection
