@extends('layouts.app')

@section('title', $s['plural'])

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">{{ $s['plural'] }}</h1>
            <p class="page-subtitle">{{ $s['subtitle'] }}</p>
        </div>
        <a href="{{ route($s['route'].'.create') }}" class="btn btn-primary">New {{ $s['singular'] }}</a>
    </div>

    <form method="GET" action="{{ route($s['route'].'.index') }}" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search {{ strtolower($s['plural']) }}…">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Search</button>
            @if ($q !== '')
                <a href="{{ route($s['route'].'.index') }}" class="btn btn-link">Clear</a>
            @endif
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    @foreach ($s['columns'] as $col)
                        <th>{{ $col['label'] }}</th>
                    @endforeach
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($s['columns'] as $col)
                            <td>
                                @if (($col['type'] ?? null) === 'status')
                                    @if ($row->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-danger">Inactive</span>
                                    @endif
                                @else
                                    {{ data_get($row, $col['key']) ?? '—' }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end">
                            <a href="{{ route($s['route'].'.edit', $row) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($s['columns']) + 1 }}" class="text-center text-muted py-4">
                            No {{ strtolower($s['plural']) }} found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $rows->links() }}
    </div>
@endsection
