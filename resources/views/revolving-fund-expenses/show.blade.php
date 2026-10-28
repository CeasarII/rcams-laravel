@extends('layouts.app')

@section('title', $expense->control_no)

@section('content')
    @php
        $fund = $expense->voyage->revolvingFund;
        $items = $expense->items;
        $questionable = $items->filter(fn ($i) => $i->status->code !== 'EXPENSE');
    @endphp

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $expense->control_no }}</h1>
            <x-status-badge :status="$expense->status" class="fs-6" />
            <span class="text-muted ms-2 small">
                Revolving fund
                @can('view', $fund)
                    <a href="{{ route('revolving-funds.show', $fund) }}">{{ $fund->fund_code }}</a>
                @else
                    {{ $fund->fund_code }}
                @endcan
                &middot; voyage {{ $expense->voyage->voyage_no }}
            </span>
        </div>
        @can('update', $expense)
            <a href="{{ route('revolving-fund-expenses.edit', $expense) }}" class="btn btn-outline-secondary">Edit Details</a>
        @endcan
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-header">Voyage Expense Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Fund</dt>
                        <dd class="col-sm-8">{{ $fund->fund_code }} &mdash; {{ $fund->fund_name }}</dd>

                        <dt class="col-sm-4">Vessel / Department</dt>
                        <dd class="col-sm-8">{{ $fund->vessel?->name ?? 'Shore Base' }} &middot; {{ $fund->department->name }}</dd>

                        <dt class="col-sm-4">Prepared by</dt>
                        <dd class="col-sm-8">{{ $expense->preparer->full_name }}</dd>

                        <dt class="col-sm-4">Expense date</dt>
                        <dd class="col-sm-8">{{ $expense->expense_date->format('Y-m-d') }}</dd>

                        <dt class="col-sm-4">Remarks</dt>
                        <dd class="col-sm-8">{{ $expense->remarks ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Expense Breakdown</span>
                    @can('update', $expense)
                        <a href="{{ route('revolving-fund-expenses.items.create', $expense) }}" class="btn btn-sm btn-primary">Add Item</a>
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
                                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 text-muted"><path d="M21 12.5l-8.5 8.5a5 5 0 0 1-7-7L14 5.5a3.5 3.5 0 0 1 5 5L10.5 19a2 2 0 0 1-3-3L15 8.5"/></svg><a href="{{ route('revolving-fund-expenses.items.attachments.download', [$expense, $item, $attachment]) }}" title="{{ $attachment->original_file_name }}">{{ $attachment->original_file_name }}</a>
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
                                        @if ($qe->status->code === 'TAGGED')<div class="text-muted">HC pending not yet set</div>@endif
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('update', $expense)
                                        <a href="{{ route('revolving-fund-expenses.items.edit', [$expense, $item]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        <form method="POST" action="{{ route('revolving-fund-expenses.items.destroy', [$expense, $item]) }}"
                                              class="d-inline" onsubmit="return confirm('Remove this item?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    @endcan

                                    @can('tagQuestionable', $expense)
                                        @if ($item->status->code === 'EXPENSE')
                                            <details class="mt-1 text-start">
                                                <summary class="text-danger" style="cursor:pointer">Tag as Questionable</summary>
                                                <form method="POST" action="{{ route('revolving-fund-expenses.items.tag', [$expense, $item]) }}" class="mt-1">
                                                    @csrf
                                                    <input type="text" name="reason" required maxlength="1000"
                                                           class="form-control form-control-sm mb-1" placeholder="Why is it questionable?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Tag</button>
                                                </form>
                                            </details>
                                        @elseif ($qe && $qe->tagged_by === auth()->id() && $qe->status->code === 'TAGGED')
                                            <form method="POST" action="{{ route('revolving-fund-expenses.items.untag', [$expense, $item]) }}"
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
                                <th colspan="6" class="text-end">Total (replenishment amount)</th>
                                <th class="text-end">{{ number_format($items->sum('gross_amount'), 2) }}</th>
                                <th class="text-end">{{ number_format($items->sum('net_amount'), 2) }}</th>
                                <th class="text-end">{{ number_format($items->sum('input_tax'), 2) }}</th>
                                <th colspan="2"></th>
                            </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            @if ($expense->voucher)
                <div class="card mb-4">
                    <div class="card-header">AP Voucher &amp; Disbursement</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">APV No.</dt>
                            <dd class="col-sm-8">{{ $expense->voucher->apv_no }} ({{ $expense->voucher->apv_date->format('Y-m-d') }}) &mdash; {{ number_format($expense->voucher->amount, 2) }}</dd>
                            @if ($expense->voucher->cv_no)
                                <dt class="col-sm-4">CV No.</dt>
                                <dd class="col-sm-8">{{ $expense->voucher->cv_no }}</dd>
                            @endif
                            @if ($expense->voucher->disbursed_at)
                                <dt class="col-sm-4">Disbursed</dt>
                                <dd class="col-sm-8">
                                    {{ $expense->voucher->disbursed_at->format('Y-m-d H:i') }}
                                    @if ($expense->voucher->payment_mode) &middot; {{ $expense->voucher->payment_mode }} @endif
                                    @if ($expense->voucher->payment_reference) &middot; {{ $expense->voucher->payment_reference }} @endif
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
                    @forelse ($expense->approvals as $entry)
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
                        <dt class="col-7">Replenishment amount</dt>
                        <dd class="col-5 text-end fw-bold">{{ number_format($expense->total_amount, 2) }}</dd>

                        <dt class="col-7">Of which questionable</dt>
                        <dd class="col-5 text-end">{{ number_format($questionable->sum('gross_amount'), 2) }}</dd>

                        <dt class="col-7">Fund limit</dt>
                        <dd class="col-5 text-end">{{ number_format($fund->fund_limit, 2) }}</dd>

                        <dt class="col-7">Fund balance</dt>
                        <dd class="col-5 text-end {{ $fund->current_balance < 0 ? 'text-danger' : '' }}">{{ number_format($fund->current_balance, 2) }}</dd>
                    </dl>
                    <p class="text-muted small mt-3 mb-0">
                        Questionable items are replenished with the rest, but stay Advances to Employee until their hard copy is validated.
                        Approval posts the expenses against the fund; disbursement restores it.
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Actions</div>
                <div class="card-body d-grid gap-2">

                    @can('submit', $expense)
                        <form method="POST" action="{{ route('revolving-fund-expenses.submit', $expense) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">Submit for OE Validation</button>
                        </form>
                    @endcan

                    @can('validateAsOe', $expense)
                        <p class="small text-muted mb-0">Verify the soft copy. Tag any item you question in the table first, then validate.</p>
                        <form method="POST" action="{{ route('revolving-fund-expenses.validate-oe', $expense) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Valid &mdash; Validate</button>
                        </form>
                    @endcan
                    @can('returnToRequestor', $expense)
                        <form method="POST" action="{{ route('revolving-fund-expenses.return-to-requestor', $expense) }}"
                              onsubmit="return confirm('Return to the Requestor to revise?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="What needs revising? (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Not valid &mdash; Return to Requestor</button>
                        </form>
                    @endcan

                    @can('validateAsStaff', $expense)
                        <p class="small text-muted mb-0">Validate the replenishment details. You can still tag questionable items in the table.</p>
                        <form method="POST" action="{{ route('revolving-fund-expenses.validate-staff', $expense) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Valid &mdash; Validate</button>
                        </form>
                        <form method="POST" action="{{ route('revolving-fund-expenses.return-to-oe', $expense) }}"
                              onsubmit="return confirm('Return to the Approver/OE to revise?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="What needs revising? (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Not valid &mdash; Return to Approver/OE</button>
                        </form>
                    @endcan

                    @can('decide', $expense)
                        <form method="POST" action="{{ route('revolving-fund-expenses.approve', $expense) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('revolving-fund-expenses.not-approve', $expense) }}"
                              onsubmit="return confirm('Send back to Accounting Staff?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Reason (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Not Approved &mdash; Send Back</button>
                        </form>
                    @endcan

                    @can('createApv', $expense)
                        <form method="POST" action="{{ route('revolving-fund-expenses.ap-voucher', $expense) }}">
                            @csrf
                            <label class="form-label small">APV No. (amount {{ number_format($expense->total_amount, 2) }})</label>
                            <input type="text" name="apv_no" class="form-control mb-2" required maxlength="30">
                            <button type="submit" class="btn btn-primary w-100">Create AP Voucher</button>
                        </form>
                    @endcan

                    @can('setHcPending', $expense)
                        <form method="POST" action="{{ route('revolving-fund-expenses.hc-pending', $expense) }}">
                            @csrf
                            <p class="small mb-2">Set the tag status of the questionable items to <strong>Advances to Employee &mdash; HC pending</strong>.</p>
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-primary w-100">Set Tag Status: HC Pending</button>
                        </form>
                    @endcan

                    @can('provideCv', $expense)
                        <form method="POST" action="{{ route('revolving-fund-expenses.cv-number', $expense) }}">
                            @csrf
                            <label class="form-label small">CV No.</label>
                            <input type="text" name="cv_no" class="form-control mb-2" required maxlength="30">
                            <button type="submit" class="btn btn-primary w-100">Create CV Number</button>
                        </form>
                    @endcan

                    @can('disburse', $expense)
                        <form method="POST" action="{{ route('revolving-fund-expenses.disburse', $expense) }}">
                            @csrf
                            <label class="form-label small">Payment Mode</label>
                            <input type="text" name="payment_mode" class="form-control mb-2" maxlength="30" placeholder="e.g. Check, Bank Transfer">
                            <label class="form-label small">Reference No.</label>
                            <input type="text" name="payment_reference" class="form-control mb-2" maxlength="100">
                            <button type="submit" class="btn btn-primary w-100">Disburse &amp; Notify Requestor</button>
                        </form>
                    @endcan

                    @canany(['submit', 'update', 'validateAsOe', 'validateAsStaff', 'decide', 'createApv', 'setHcPending', 'provideCv', 'disburse'], $expense)
                    @else
                        <p class="text-muted mb-0">No action available at this stage for your role.</p>
                    @endcanany
                </div>
            </div>
        </div>
    </div>
@endsection
