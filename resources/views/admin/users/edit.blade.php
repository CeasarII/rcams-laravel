@extends('layouts.app')

@section('title', 'Edit ' . $user->full_name)

@section('content')
    <h1 class="h3 mb-4">Edit {{ $user->full_name }}</h1>

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="card p-4">
        @csrf
        @method('PUT')
        @include('admin.users._form', ['user' => $user])

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
@endsection
