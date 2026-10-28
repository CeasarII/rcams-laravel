@extends('layouts.app')

@section('title', $item ? 'Edit Item' : 'Add Item')

@section('content')
    <h1 class="h3 mb-1">{{ $item ? 'Edit Item' : 'Add Item' }}</h1>
    <p class="text-muted">{{ $expense->control_no }} &middot; voyage {{ $expense->voyage->voyage_no }} &middot; enter the amount exactly as printed on the receipt (VAT-inclusive).</p>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $item ? route('revolving-fund-expenses.items.update', [$expense, $item]) : route('revolving-fund-expenses.items.store', $expense) }}"
          class="card p-4 mb-4">
        @csrf
        @if ($item) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="item_date">Date <span class="text-danger">*</span></label>
                <input type="date" id="item_date" name="item_date" required max="{{ now()->toDateString() }}"
                       class="form-control @error('item_date') is-invalid @enderror"
                       value="{{ old('item_date', $item?->item_date?->format('Y-m-d')) }}">
                @error('item_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-9">
                <label class="form-label" for="purpose">Purpose <span class="text-danger">*</span></label>
                <input type="text" id="purpose" name="purpose" required maxlength="255"
                       class="form-control @error('purpose') is-invalid @enderror" value="{{ old('purpose', $item?->purpose) }}">
                @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" rows="2" maxlength="2000"
                          class="form-control @error('description') is-invalid @enderror">{{ old('description', $item?->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="supplier_name">Supplier <span class="text-danger">*</span></label>
                <input type="text" id="supplier_name" name="supplier_name" required maxlength="150"
                       class="form-control @error('supplier_name') is-invalid @enderror" value="{{ old('supplier_name', $item?->supplier_name) }}">
                @error('supplier_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="tin">Supplier TIN <span class="text-danger" id="tin-star">*</span></label>
                <input type="text" id="tin" name="tin" maxlength="20" placeholder="000-000-000-000"
                       class="form-control @error('tin') is-invalid @enderror" value="{{ old('tin', $item?->tin) }}">
                @error('tin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text" id="tin-help">Required for VAT receipts.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="reference">OR / Invoice No.</label>
                <input type="text" id="reference" name="reference" maxlength="100"
                       class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference', $item?->reference) }}">
                @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="nature_of_cost_id">Nature of Cost <span class="text-danger">*</span></label>
                <select id="nature_of_cost_id" name="nature_of_cost_id" required class="form-select @error('nature_of_cost_id') is-invalid @enderror">
                    <option value="">Select&hellip;</option>
                    @foreach ($natures as $nature)
                        <option value="{{ $nature->id }}" @selected((string) old('nature_of_cost_id', $item?->nature_of_cost_id) === (string) $nature->id)>{{ $nature->label }}</option>
                    @endforeach
                </select>
                @error('nature_of_cost_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="vat_nature">VAT <span class="text-danger">*</span></label>
                <select id="vat_nature" name="vat_nature" required class="form-select @error('vat_nature') is-invalid @enderror">
                    @foreach (\App\Support\PhVat::natures() as $value => $label)
                        <option value="{{ $value }}" @selected(old('vat_nature', $item?->vat_nature ?? 'VAT') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('vat_nature')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Non-VAT also covers VAT-exempt and zero-rated purchases (no input tax).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="gross_amount">Amount on receipt (VAT-inclusive) <span class="text-danger">*</span></label>
                <input type="number" id="gross_amount" name="gross_amount" required step="0.01" min="0.01"
                       class="form-control @error('gross_amount') is-invalid @enderror" value="{{ old('gross_amount', $item?->gross_amount) }}">
                @error('gross_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <div class="border rounded p-3 bg-light small d-flex flex-wrap gap-4">
                    <div>Net of VAT: <strong id="pv-net">0.00</strong></div>
                    <div>Input VAT (<span id="pv-rate">{{ rtrim(rtrim(number_format($vatRate, 2), '0'), '.') }}</span>%): <strong id="pv-tax">0.00</strong></div>
                    <div>Gross: <strong id="pv-gross">0.00</strong></div>
                    <div class="text-muted">Preview only &mdash; the system recomputes these when you save.</div>
                </div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12">
                <label class="form-label" for="files">Soft copy <span class="text-muted">(PDF, JPG, PNG &middot; up to 5 files, 5 MB each)</span></label>
                <input type="file" id="files" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png"
                       class="form-control @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror">
                @error('files')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @error('files.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Every item needs at least one supporting document (soft copy) before the expenses can be submitted. The hard copy is tracked afterwards for questionable items.</div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">{{ $item ? 'Save Item' : 'Add Item' }}</button>
            <a href="{{ route('revolving-fund-expenses.show', $expense) }}" class="btn btn-link">Cancel</a>
        </div>
    </form>

    @if ($item && $item->attachments->isNotEmpty())
        <div class="card">
            <div class="card-header">Attached documents</div>
            <ul class="list-group list-group-flush">
                @foreach ($item->attachments as $attachment)
                    <li class="list-group-item small d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ route('revolving-fund-expenses.items.attachments.download', [$expense, $item, $attachment]) }}">{{ $attachment->original_file_name }}</a>
                            <span class="text-muted">&middot; {{ $attachment->humanSize() }}</span>
                        </div>
                        <form method="POST" action="{{ route('revolving-fund-expenses.items.attachments.destroy', [$expense, $item, $attachment]) }}"
                              onsubmit="return confirm('Remove this attachment?');" class="mb-0">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <script>
    (function () {
        const rate = {{ $vatRate }};
        const gross = document.getElementById('gross_amount');
        const nature = document.getElementById('vat_nature');
        const star = document.getElementById('tin-star');
        const tinHelp = document.getElementById('tin-help');
        const money = (n) => n.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        function update() {
            const isVat = nature.value === 'VAT';
            const cents = Math.round((parseFloat(gross.value) || 0) * 100);
            // Same rule as the server (PhVat::split): tax = gross x rate / (100 + rate), rounded once.
            const tax = isVat ? Math.round(cents * rate / (100 + rate)) : 0;
            document.getElementById('pv-net').textContent = money((cents - tax) / 100);
            document.getElementById('pv-tax').textContent = money(tax / 100);
            document.getElementById('pv-gross').textContent = money(cents / 100);
            document.getElementById('pv-rate').textContent = isVat ? String(rate) : '0';
            star.style.display = isVat ? '' : 'none';
            tinHelp.textContent = isVat ? 'Required for VAT receipts.' : 'Optional for Non-VAT receipts.';
        }

        gross.addEventListener('input', update);
        nature.addEventListener('change', update);
        update();
    })();
    </script>
@endsection
