{{-- Expects: $fundRequest (nullable), $requestorDepartment, $vessels (the requestor's assigned vessels), $fundRequestTypes --}}

@php
    $lines = old('details', optional($fundRequest ?? null)->details?->map(fn ($d) => [
        'purpose' => $d->purpose, 'description' => $d->description, 'amount' => $d->amount,
    ])->toArray() ?? [['purpose' => '', 'description' => '', 'amount' => '']]);
@endphp

<div class="row g-3">
    @php $hasVessels = $vessels->isNotEmpty(); @endphp

    <div class="col-md-4">
        <label class="form-label">Category</label>
        @if ($hasVessels)
            <select name="category" id="category" class="form-select" required>
                <option value="Sea Base" @selected(old('category', $fundRequest->category ?? 'Sea Base') === 'Sea Base')>Sea Base</option>
                <option value="Shore Base" @selected(old('category', $fundRequest->category ?? 'Sea Base') === 'Shore Base')>Shore Base</option>
            </select>
        @else
            <input type="text" class="form-control" value="Shore Base" disabled>
            <input type="hidden" name="category" id="category" value="Shore Base">
            <div class="form-text">No vessel is assigned to your account.</div>
        @endif
    </div>

    @if ($hasVessels)
        <div class="col-md-4" id="vessel-wrapper">
            <label class="form-label">Vessel</label>
            @if ($vessels->count() === 1)
                <input type="text" class="form-control" value="{{ $vessels->first()->name }}" disabled>
                <input type="hidden" name="vessel_id" value="{{ $vessels->first()->id }}">
                <div class="form-text">Set from your account.</div>
            @else
                <select name="vessel_id" class="form-select">
                    <option value="">—</option>
                    @foreach ($vessels as $vessel)
                        <option value="{{ $vessel->id }}" @selected(old('vessel_id', $fundRequest->vessel_id ?? '') == $vessel->id)>{{ $vessel->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Required for Sea Base. Your assigned vessels only.</div>
            @endif
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label">Fund Request Type</label>
        <select name="fund_request_type_id" id="fund_request_type_id" class="form-select" required>
            @foreach ($fundRequestTypes as $type)
                <option value="{{ $type->id }}" data-code="{{ $type->code }}" @selected(old('fund_request_type_id', $fundRequest->fund_request_type_id ?? '') == $type->id)>{{ $type->label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-8" id="rf-wrapper" style="display: none;">
        <label class="form-label">Revolving Fund <span class="text-danger">*</span></label>
        <select name="revolving_fund_id" id="revolving_fund_id" class="form-select @error('revolving_fund_id') is-invalid @enderror">
            <option value="">Select the fund this request funds&hellip;</option>
            @foreach ($revolvingFunds as $fund)
                <option value="{{ $fund->id }}" @selected(old('revolving_fund_id', $fundRequest->revolving_fund_id ?? '') == $fund->id)>
                    {{ $fund->fund_code }} &mdash; {{ $fund->fund_name }}{{ $fund->vessel ? ' ('.$fund->vessel->name.')' : '' }}
                    &middot; limit {{ number_format($fund->fund_limit, 2) }}, balance {{ number_format($fund->current_balance, 2) }}
                </option>
            @endforeach
        </select>
        @error('revolving_fund_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">
            @if ($revolvingFunds->isEmpty())
                You are not the custodian of any active revolving fund. Ask an Admin to set one up.
            @else
                When disbursed, the amount is added to the fund's balance (never above its limit).
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Department</label>
        <input type="text" class="form-control" value="{{ $requestorDepartment?->name }}" disabled>
        <div class="form-text">Set from your account.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Request Date</label>
        <input type="date" name="request_date" class="form-control"
               value="{{ old('request_date', optional($fundRequest ?? null)->request_date?->format('Y-m-d')) }}" required>
    </div>

    <div class="col-12">
        <label class="form-label">Purpose</label>
        <textarea name="purpose" class="form-control" rows="2" required>{{ old('purpose', $fundRequest->purpose ?? '') }}</textarea>
    </div>
</div>

<hr class="my-4">

<h2 class="h5">Detail Lines</h2>
<table class="table" id="detail-lines">
    <thead>
    <tr>
        <th>Purpose</th>
        <th>Description</th>
        <th style="width: 160px;">Amount</th>
        <th style="width: 40px;"></th>
    </tr>
    </thead>
    <tbody>
    @foreach ($lines as $i => $line)
        <tr>
            <td><input type="text" name="details[{{ $i }}][purpose]" class="form-control" value="{{ $line['purpose'] }}" required></td>
            <td><input type="text" name="details[{{ $i }}][description]" class="form-control" value="{{ $line['description'] }}"></td>
            <td><input type="number" step="0.01" min="0.01" name="details[{{ $i }}][amount]" class="form-control amount-input" value="{{ $line['amount'] }}" required></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">&times;</button></td>
        </tr>
    @endforeach
    </tbody>
</table>

<button type="button" id="add-line" class="btn btn-sm btn-outline-secondary">+ Add line</button>

<div class="text-end mt-2">
    <strong>Total (computed server-side on save): <span id="running-total">0.00</span></strong>
</div>

<template id="line-template">
    <tr>
        <td><input type="text" name="details[__INDEX__][purpose]" class="form-control" required></td>
        <td><input type="text" name="details[__INDEX__][description]" class="form-control"></td>
        <td><input type="number" step="0.01" min="0.01" name="details[__INDEX__][amount]" class="form-control amount-input" required></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">&times;</button></td>
    </tr>
</template>

<script>
(function () {
    const categorySelect = document.getElementById('category');
    const vesselWrapper = document.getElementById('vessel-wrapper'); // absent when no vessel is assigned

    function toggleVessel() {
        if (vesselWrapper) {
            vesselWrapper.style.display = categorySelect.value === 'Sea Base' ? '' : 'none';
        }
    }
    categorySelect.addEventListener('change', toggleVessel);
    toggleVessel();

    const tbody = document.querySelector('#detail-lines tbody');
    const template = document.getElementById('line-template').innerHTML;
    let index = {{ count($lines) }};

    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.amount-input').forEach(function (input) {
            total += parseFloat(input.value || 0);
        });
        document.getElementById('running-total').textContent = total.toFixed(2);
    }

    document.getElementById('add-line').addEventListener('click', function () {
        const row = document.createElement('tr');
        row.innerHTML = template.replaceAll('__INDEX__', index++);
        tbody.appendChild(row);
    });

    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-line')) {
            if (tbody.rows.length > 1) {
                e.target.closest('tr').remove();
                recalcTotal();
            }
        }
    });

    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('amount-input')) recalcTotal();
    });

    recalcTotal();
})();
</script>

<script>
(function () {
    const type = document.getElementById('fund_request_type_id');
    const wrapper = document.getElementById('rf-wrapper');
    const fund = document.getElementById('revolving_fund_id');

    function toggleFund() {
        const isRf = type.options[type.selectedIndex]?.dataset.code === 'REVOLVING_FUND';
        wrapper.style.display = isRf ? '' : 'none';
        fund.required = isRf;
        if (! isRf) fund.value = '';
    }

    type.addEventListener('change', toggleFund);
    toggleFund();
})();
</script>
