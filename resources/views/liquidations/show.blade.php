@extends('layouts.app')

@section('title', $liquidation->control_no)

@section('content')
    @php
        $request = $liquidation->fundRequest;
        $items = $liquidation->items;
        $validItems = $items->filter(fn ($i) => $i->status->code === 'EXPENSE');
    @endphp

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $liquidation->control_no }}</h1>
            <x-status-badge :status="$liquidation->status" class="fs-6" />
            <span class="text-muted ms-2 small">Cash Advance {{ $request->control_no }}</span>
        </div>
        @can('update', $liquidation)
            <a href="{{ route('liquidations.edit', $liquidation) }}" class="btn btn-outline-secondary">Edit Details</a>
        @endcan
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-header">Liquidation Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Requestor</dt>
                        <dd class="col-sm-8">{{ $liquidation->preparer->full_name }}</dd>

                        <dt class="col-sm-4">Cash Advance</dt>
                        <dd class="col-sm-8">
                            @can('view', $request)
                                <a href="{{ route('fund-requests.show', $request) }}">{{ $request->control_no }}</a>
                            @else
                                {{ $request->control_no }}
                            @endcan
                            &mdash; {{ number_format($liquidation->cash_advance_amount, 2) }}
                        </dd>

                        <dt class="col-sm-4">Category</dt>
                        <dd class="col-sm-8">{{ $request->category }}@if ($request->vessel) &mdash; {{ $request->vessel->name }}@endif</dd>

                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8">{{ $request->department->name }}</dd>

                        <dt class="col-sm-4">Date Prepared</dt>
                        <dd class="col-sm-8">{{ $liquidation->date_prepared->format('Y-m-d') }}</dd>

                        <dt class="col-sm-4">Purpose</dt>
                        <dd class="col-sm-8">{{ $liquidation->purpose ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Expense Breakdown</span>
                    @can('update', $liquidation)
                        <a href="{{ route('liquidations.items.create', $liquidation) }}" class="btn btn-sm btn-primary">Add Item</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th><th>Supplier / Purpose</th><th>Reference</th><th>TIN</th><th>Nature</th><th>VAT</th>
                            <th class="text-end">Gross</th><th class="text-end">Net</th><th class="text-end">Input VAT</th>
                            <th>Status</th><th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($items as $item)
                            @php $qe = $item->questionableExpense; @endphp
                            <tr>
                                <td class="text-nowrap">{{ $item->item_date->format('Y-m-d') }}</td>
                                <td style="min-width: 17rem;">
                                    <div class="fw-semibold">{{ $item->supplier_name }}</div>
                                    <div>{{ $item->purpose }}</div>
                                    @if ($item->description)
                                        <div class="text-muted">{{ $item->description }}</div>
                                    @endif
                                    @foreach ($item->attachments as $attachment)
                                        <div class="text-truncate" style="max-width: 17rem;">
                                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 text-muted"><path d="M21 12.5l-8.5 8.5a5 5 0 0 1-7-7L14 5.5a3.5 3.5 0 0 1 5 5L10.5 19a2 2 0 0 1-3-3L15 8.5"/></svg><a href="{{ route('liquidations.items.attachments.download', [$liquidation, $item, $attachment]) }}" title="{{ $attachment->file_name }}">{{ $attachment->file_name }}</a> <span class="text-muted">({{ $attachment->documentType->label }})</span>
                                        </div>
                                    @endforeach
                                </td>
                                <td class="text-nowrap">{{ $item->reference ?: '—' }}</td>
                                <td class="text-nowrap">{{ $item->tin ?: '—' }}</td>
                                <td>{{ $item->natureOfCost->label }}</td>
                                <td class="text-nowrap">
                                    @if ($item->vat_nature === 'VAT')
                                        VAT {{ rtrim(rtrim(number_format($item->vat_rate, 2), '0'), '.') }}%
                                    @else
                                        Non-VAT
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">{{ number_format($item->gross_amount, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($item->net_amount, 2) }}</td>
                                <td class="text-end text-nowrap">{{ number_format($item->input_tax, 2) }}</td>
                                <td>
                                    <x-status-badge :status="$item->status" />
                                    @if ($qe)
                                        <div><a href="{{ route('questionable-expenses.show', $qe) }}">{{ $qe->control_no }}</a></div>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('update', $liquidation)
                                        @unless ($qe)
                                            <a href="{{ route('liquidations.items.edit', [$liquidation, $item]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                            <form method="POST" action="{{ route('liquidations.items.destroy', [$liquidation, $item]) }}"
                                                  class="d-inline" onsubmit="return confirm('Remove this item?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        @endunless
                                    @endcan

                                    @can('tagQuestionable', $liquidation)
                                        @if ($item->status->code === 'EXPENSE')
                                            <details class="mt-1 text-start">
                                                <summary class="text-danger" style="cursor:pointer">Tag as Questionable</summary>
                                                <form method="POST" action="{{ route('liquidations.items.tag', [$liquidation, $item]) }}" class="mt-1">
                                                    @csrf
                                                    <input type="text" name="reason" required maxlength="1000"
                                                           class="form-control form-control-sm mb-1" placeholder="Why is it questionable?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Tag</button>
                                                </form>
                                            </details>
                                        @elseif ($qe && $qe->tagged_by === auth()->id() && $qe->status->code === 'WAITING_HARD_COPY')
                                            <form method="POST" action="{{ route('liquidations.items.untag', [$liquidation, $item]) }}"
                                                  class="mt-1" onsubmit="return confirm('Remove the questionable tag?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Remove tag</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted py-4">No items yet.</td></tr>
                        @endforelse
                        </tbody>
                        @if ($items->isNotEmpty())
                            <tfoot class="table-light">
                            <tr>
                                <th colspan="6" class="text-end">All items</th>
                                <th class="text-end">{{ number_format($items->sum('gross_amount'), 2) }}</th>
                                <th class="text-end">{{ number_format($items->sum('net_amount'), 2) }}</th>
                                <th class="text-end">{{ number_format($items->sum('input_tax'), 2) }}</th>
                                <th colspan="2"></th>
                            </tr>
                            <tr>
                                <th colspan="6" class="text-end">Valid expenses only (creditable input VAT)</th>
                                <th class="text-end">{{ number_format($validItems->sum('gross_amount'), 2) }}</th>
                                <th class="text-end">{{ number_format($validItems->sum('net_amount'), 2) }}</th>
                                <th class="text-end">{{ number_format($validItems->sum('input_tax'), 2) }}</th>
                                <th colspan="2"></th>
                            </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            @if ($liquidation->voucher)
                <div class="card mb-4">
                    <div class="card-header">AP Voucher &amp; Reimbursement</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">APV No.</dt>
                            <dd class="col-sm-8">{{ $liquidation->voucher->apv_no }} ({{ $liquidation->voucher->apv_date->format('Y-m-d') }}) &mdash; {{ number_format($liquidation->voucher->amount, 2) }}</dd>
                            @if ($liquidation->voucher->cv_no)
                                <dt class="col-sm-4">CV No.</dt>
                                <dd class="col-sm-8">{{ $liquidation->voucher->cv_no }}</dd>
                                <dt class="col-sm-4">Payment</dt>
                                <dd class="col-sm-8">
                                    {{ $liquidation->voucher->payment_mode ?: '—' }}
                                    @if ($liquidation->voucher->payment_reference) &middot; {{ $liquidation->voucher->payment_reference }} @endif
                                    @if ($liquidation->voucher->reimbursed_at) &middot; {{ $liquidation->voucher->reimbursed_at->format('Y-m-d H:i') }} @endif
                                </dd>
                            @endif
                        </dl>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">Workflow History</div>
                <table class="table mb-0 small">
                    <thead><tr><th>When</th><th>Role</th><th>By</th><th>Action</th><th>From</th><th>To</th><th>Comments</th></tr></thead>
                    <tbody>
                    @forelse ($liquidation->approvals as $entry)
                        <tr>
                            <td class="text-nowrap">{{ $entry->approval_date->format('Y-m-d H:i') }}</td>
                            <td>{{ $entry->role->label }}</td>
                            <td>{{ $entry->approver->full_name }}</td>
                            <td>{{ $entry->approvalStatus->label }}</td>
                            <td>{{ $entry->fromStatus->label ?? '—' }}</td>
                            <td>{{ $entry->toStatus->label }}</td>
                            <td>{{ $entry->comments }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No activity yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Summary</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7">Cash advance</dt>
                        <dd class="col-5 text-end">{{ number_format($liquidation->cash_advance_amount, 2) }}</dd>

                        <dt class="col-7">Total expenses</dt>
                        <dd class="col-5 text-end">{{ number_format($liquidation->total_amount, 2) }}</dd>

                        <dt class="col-7">Liquidated</dt>
                        <dd class="col-5 text-end">{{ number_format($liquidation->liquidated_amount, 2) }}</dd>

                        <dt class="col-7">Held as Advances to Employee</dt>
                        <dd class="col-5 text-end">{{ number_format($heldAsAdvances, 2) }}</dd>

                        <dt class="col-7">To return</dt>
                        <dd class="col-5 text-end">{{ number_format($liquidation->amount_to_return, 2) }}</dd>

                        <dt class="col-7">To reimburse</dt>
                        <dd class="col-5 text-end fw-bold">{{ number_format($liquidation->amount_to_reimburse, 2) }}</dd>
                    </dl>
                    <p class="text-muted small mt-3 mb-0">
                        Questionable items are not counted as liquidated until their Questionable Expense is cleared.
                        Figures follow the items until Accounting verifies, then they are fixed for the voucher.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Actions</div>
                <div class="card-body d-grid gap-2">

                    @can('update', $liquidation)
                        <form method="POST" action="{{ route('liquidations.submit', $liquidation) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">Submit for OE Validation</button>
                        </form>
                    @endcan

                    @can('validateAsOe', $liquidation)
                        <p class="small text-muted mb-0">Liquidation okay? Tag any item you question in the table first, then validate.</p>
                        <form method="POST" action="{{ route('liquidations.validate-oe', $liquidation) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Liquidation okay &mdash; Validate</button>
                        </form>
                        <form method="POST" action="{{ route('liquidations.return-for-edit', $liquidation) }}"
                              onsubmit="return confirm('Return to the Requestor to edit?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="What needs editing? (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Not okay &mdash; Return for Edit</button>
                        </form>
                    @endcan

                    @can('verifyAsStaff', $liquidation)
                        <p class="small text-muted mb-0">Verification okay? You can still tag questionable items in the table.</p>
                        <form method="POST" action="{{ route('liquidations.verify', $liquidation) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Verification okay &mdash; Verify</button>
                        </form>
                        <form method="POST" action="{{ route('liquidations.return-for-revision', $liquidation) }}"
                              onsubmit="return confirm('Return to the Requestor to revise?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="What needs revising? (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Not okay &mdash; Return for Revision</button>
                        </form>
                    @endcan

                    @can('prepareApv', $liquidation)
                        <form method="POST" action="{{ route('liquidations.prepare-apv', $liquidation) }}">
                            @csrf
                            <label class="form-label small">APV No. (amount payable to requestor: {{ number_format($liquidation->amount_to_reimburse, 2) }})</label>
                            <input type="text" name="apv_no" class="form-control mb-2" required maxlength="30" value="{{ old('apv_no', $liquidation->voucher?->apv_no) }}">
                            <button type="submit" class="btn btn-primary w-100">Prepare Accounts Payable Voucher</button>
                        </form>
                    @endcan

                    @can('reviewApv', $liquidation)
                        <form method="POST" action="{{ route('liquidations.approve-apv', $liquidation) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Review okay &mdash; Approve APV</button>
                        </form>
                        <form method="POST" action="{{ route('liquidations.reject-apv', $liquidation) }}"
                              onsubmit="return confirm('Send back to Accounting Staff?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Reason (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Not okay &mdash; Send Back</button>
                        </form>
                    @endcan

                    @can('close', $liquidation)
                        <form method="POST" action="{{ route('liquidations.close', $liquidation) }}">
                            @csrf
                            @if ((float) $liquidation->amount_to_return > 0)
                                <label class="form-label small">Unspent cash to return: {{ number_format($liquidation->amount_to_return, 2) }} &mdash; receipt / reference</label>
                                <input type="text" name="cash_return_reference" class="form-control mb-2" required maxlength="100">
                            @endif
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-primary w-100">Process Liquidation &amp; Close</button>
                        </form>
                    @endcan

                    @can('reimburse', $liquidation)
                        <form method="POST" action="{{ route('liquidations.reimburse', $liquidation) }}">
                            @csrf
                            <p class="small mb-2">Amount to reimburse: <strong>{{ number_format($liquidation->amount_to_reimburse, 2) }}</strong></p>
                            <label class="form-label small">Payment Mode</label>
                            <input type="text" name="payment_mode" class="form-control mb-2" maxlength="30" placeholder="e.g. Check, Bank Transfer">
                            <label class="form-label small">Reference No.</label>
                            <input type="text" name="payment_reference" class="form-control mb-2" maxlength="100">
                            <label class="form-label small">CV No.</label>
                            <input type="text" name="cv_no" class="form-control mb-2" required maxlength="30">
                            <button type="submit" class="btn btn-primary w-100">Reimburse, Input CV &amp; Notify Requestor</button>
                        </form>
                    @endcan

                    @canany(['update', 'validateAsOe', 'verifyAsStaff', 'prepareApv', 'reviewApv', 'close', 'reimburse'], $liquidation)
                    @else
                        <p class="text-muted mb-0">No action available at this stage for your role.</p>
                    @endcanany
                </div>
            </div>
        </div>
    </div>
@endsection
