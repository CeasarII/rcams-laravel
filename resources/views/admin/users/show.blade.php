@extends('layouts.app')

@section('title', $user->full_name)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $user->full_name }}</h1>
            @if ($user->is_active)
                <span class="badge text-bg-success">Active</span>
            @else
                <span class="badge text-bg-danger">Inactive</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary">Edit</a>
            @can('toggleActive', $user)
                <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}"
                      onsubmit="return confirm('{{ $user->is_active ? 'Deactivate' : 'Reactivate' }} this account?');">
                    @csrf
                    <button type="submit" class="btn {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                        {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Employee No.</dt>
                <dd class="col-sm-9">{{ $user->employee_no }}</dd>

                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ $user->email }}</dd>

                <dt class="col-sm-3">Company</dt>
                <dd class="col-sm-9">{{ $user->company->name }}</dd>

                <dt class="col-sm-3">Department</dt>
                <dd class="col-sm-9">{{ $user->department->name }}</dd>

                <dt class="col-sm-3">Roles</dt>
                <dd class="col-sm-9">
                    @foreach ($user->roles as $role)
                        <span class="badge text-bg-secondary">{{ $role->label }}</span>
                    @endforeach
                </dd>

                @if ($user->roles->contains('code', 'OE'))
                    <dt class="col-sm-3">OE Vessels</dt>
                    <dd class="col-sm-9">
                        @forelse ($user->vessels as $vessel)
                            <span class="badge text-bg-info">{{ $vessel->name }}</span>
                        @empty
                            <span class="text-muted">None assigned</span>
                        @endforelse
                    </dd>

                    <dt class="col-sm-3">OE Departments</dt>
                    <dd class="col-sm-9">
                        @forelse ($user->departments as $department)
                            <span class="badge text-bg-info">{{ $department->name }}</span>
                        @empty
                            <span class="text-muted">None assigned</span>
                        @endforelse
                    </dd>
                @endif
            </dl>
        </div>
    </div>

    <a href="{{ route('admin.users.index') }}" class="btn btn-link ps-0">&larr; Back to Users</a>
@endsection
