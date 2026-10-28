@extends('layouts.app')

@section('title', 'New User')

@section('content')
    <h1 class="h3 mb-4">New User</h1>

    <form method="POST" action="{{ route('admin.users.store') }}" class="card p-4">
        @csrf
        @include('admin.users._form', ['user' => null])

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
@endsection
