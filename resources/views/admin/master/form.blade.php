@extends('layouts.app')

@section('title', $model ? 'Edit '.$s['singular'] : 'New '.$s['singular'])

@section('content')
    <h1 class="h3 mb-4">{{ $model ? 'Edit '.$s['singular'] : 'New '.$s['singular'] }}</h1>

    <form method="POST"
          action="{{ $model ? route($s['route'].'.update', $model) : route($s['route'].'.store') }}"
          class="card p-4">
        @csrf
        @if ($model)
            @method('PUT')
        @endif

        <div class="row g-3">
            @foreach ($s['fields'] as $f)
                @php $name = $f['name']; @endphp
                <div class="col-md-{{ $f['col'] ?? 6 }}">
                    <label class="form-label" for="f-{{ $name }}">
                        {{ $f['label'] }}@if (! empty($f['required'])) <span class="text-danger">*</span>@endif
                    </label>

                    @if (($f['type'] ?? 'text') === 'select')
                        <select id="f-{{ $name }}" name="{{ $name }}"
                                class="form-select @error($name) is-invalid @enderror"
                                @required(! empty($f['required']))>
                            <option value="">Select…</option>
                            @foreach ($f['options']($model) as $id => $text)
                                <option value="{{ $id }}" @selected((string) old($name, $model?->{$name}) === (string) $id)>{{ $text }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" id="f-{{ $name }}" name="{{ $name }}"
                               class="form-control @error($name) is-invalid @enderror"
                               value="{{ old($name, $model?->{$name}) }}"
                               @if (! empty($f['maxlength'])) maxlength="{{ $f['maxlength'] }}" @endif
                               @required(! empty($f['required']))>
                    @endif

                    @if (! empty($f['help']))
                        <div class="form-text">{{ $f['help'] }}</div>
                    @endif
                </div>
            @endforeach

            <div class="col-12">
                <input type="hidden" name="is_active" value="0">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="f-is_active" name="is_active" value="1"
                           @checked(old('is_active', $model?->is_active ?? true))>
                    <label class="form-check-label" for="f-is_active">Active</label>
                </div>
                <div class="form-text">
                    Records are never deleted because other data references them. Untick Active to hide
                    this {{ strtolower($s['singular']) }} from new selections.
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">{{ $model ? 'Save Changes' : 'Create '.$s['singular'] }}</button>
            <a href="{{ route($s['route'].'.index') }}" class="btn btn-link">Cancel</a>
        </div>
    </form>
@endsection
