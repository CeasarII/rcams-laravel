@extends('layouts.app')

@section('title', $fundRequest->control_no)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $fundRequest->control_no }}</h1>
            <x-status-badge :status="$fundRequest->requestStatus" class="fs-6" />
        </div>
        @can('update', $fundRequest)
            <a href="{{ route('fund-requests.edit', $fundRequest) }}" class="btn btn-outline-secondary">Edit</a>
        @endcan
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Request Details</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Requestor</dt>
                        <dd class="col-sm-8">{{ $fundRequest->requestor->full_name }}</dd>

                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8">{{ $fundRequest->fundRequestType->label }}</dd>

                        <dt class="col-sm-4">Category</dt>
                        <dd class="col-sm-8">{{ $fundRequest->category }}@if($fundRequest->vessel) — {{ $fundRequest->vessel->name }}@endif</dd>
                        @if ($fundRequest->revolvingFund)
                            <dt class="col-sm-4">Revolving Fund</dt>
                            <dd class="col-sm-8">
                                @can('view', $fundRequest->revolvingFund)
                                    <a href="{{ route('revolving-funds.show', $fundRequest->revolvingFund) }}">{{ $fundRequest->revolvingFund->fund_code }} &mdash; {{ $fundRequest->revolvingFund->fund_name }}</a>
                                @else
                                    {{ $fundRequest->revolvingFund->fund_code }} &mdash; {{ $fundRequest->revolvingFund->fund_name }}
                                @endcan
                            </dd>
                        @endif

                        <dt class="col-sm-4">Department</dt>
                        <dd class="col-sm-8">{{ $fundRequest->department->name }}</dd>

                        <dt class="col-sm-4">Request Date</dt>
                        <dd class="col-sm-8">{{ $fundRequest->request_date->format('Y-m-d') }}</dd>

                        <dt class="col-sm-4">Purpose</dt>
                        <dd class="col-sm-8">{{ $fundRequest->purpose }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Detail Lines</div>
                <table class="table mb-0">
                    <thead><tr><th>Purpose</th><th>Description</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($fundRequest->details as $detail)
                        <tr>
                            <td>{{ $detail->purpose }}</td>
                            <td>{{ $detail->description }}</td>
                            <td class="text-end">{{ number_format($detail->amount, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="2" class="text-end">Total</th>
                        <th class="text-end">{{ number_format($fundRequest->amount_requested, 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>

            @if ($fundRequest->apVoucher)
                <div class="card mb-4">
                    <div class="card-header">AP Voucher &amp; Disbursement</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">APV No.</dt>
                            <dd class="col-sm-8">{{ $fundRequest->apVoucher->apv_no }} ({{ $fundRequest->apVoucher->apv_date->format('Y-m-d') }})</dd>

                            @if ($fundRequest->apVoucher->checkVoucher)
                                <dt class="col-sm-4">CV No.</dt>
                                <dd class="col-sm-8">{{ $fundRequest->apVoucher->checkVoucher->cv_no }}</dd>

                                @if ($fundRequest->apVoucher->checkVoucher->disbursed_at)
                                    <dt class="col-sm-4">Disbursed</dt>
                                    <dd class="col-sm-8">
                                        {{ number_format($fundRequest->apVoucher->checkVoucher->amount_disbursed, 2) }}
                                        on {{ $fundRequest->apVoucher->checkVoucher->disbursed_at->format('Y-m-d H:i') }}
                                    </dd>
                                @endif
                            @endif
                        </dl>
                    </div>
                </div>
            @endif

            @php $liquidation = \App\Models\CashAdvanceLiquidation::with('status')->where('fund_request_id', $fundRequest->id)->first(); @endphp
            @if ($liquidation || auth()->user()->can('liquidate', $fundRequest))
                <div class="card mb-4">
                    <div class="card-header">Cash Advance Liquidation</div>
                    <div class="card-body">
                        @if ($liquidation)
                            @can('view', $liquidation)
                                <a href="{{ route('liquidations.show', $liquidation) }}">{{ $liquidation->control_no }}</a>
                            @else
                                {{ $liquidation->control_no }}
                            @endcan
                            <x-status-badge :status="$liquidation->status" class="ms-2" />
                        @else
                            <p class="small text-muted">This cash advance has been disbursed. Liquidate it by listing what the money was spent on, with receipts.</p>
                            <form method="POST" action="{{ route('fund-requests.liquidation.store', $fundRequest) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Start Liquidation</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">Workflow History</div>
                <table class="table mb-0">
                    <thead><tr><th>When</th><th>Role</th><th>By</th><th>Action</th><th>From</th><th>To</th><th>Comments</th></tr></thead>
                    <tbody>
                    @forelse ($fundRequest->approvals as $entry)
                        <tr>
                            <td>{{ $entry->approval_date->format('Y-m-d H:i') }}</td>
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
            <div class="card">
                <div class="card-header">Actions</div>
                <div class="card-body d-grid gap-2">

                    @can('update', $fundRequest)
                        <form method="POST" action="{{ route('fund-requests.submit', $fundRequest) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">Submit for OE Validation</button>
                        </form>
                        <form method="POST" action="{{ route('fund-requests.cancel', $fundRequest) }}"
                              onsubmit="return confirm('Cancel this request?');">
                            @csrf
                            <input type="hidden" name="comments" value="Cancelled by requestor">
                            <button type="submit" class="btn btn-outline-dark w-100">Cancel Request</button>
                        </form>
                    @endcan

                    @can('validateAsOe', $fundRequest)
                        <form method="POST" action="{{ route('fund-requests.validate-oe', $fundRequest) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Validate (OE)</button>
                        </form>
                        <form method="POST" action="{{ route('fund-requests.return-to-requestor', $fundRequest) }}"
                              onsubmit="return confirm('Return this request to the Requestor?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Reason for return (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Return to Requestor</button>
                        </form>
                    @endcan

                    @can('validateAsStaff', $fundRequest)
                        <form method="POST" action="{{ route('fund-requests.validate-staff', $fundRequest) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Validate (Accounting Staff)</button>
                        </form>
                        <form method="POST" action="{{ route('fund-requests.return-to-oe', $fundRequest) }}"
                              onsubmit="return confirm('Return this request to the Approver/OE?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Reason for return (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Return to OE</button>
                        </form>
                    @endcan

                    @can('approve', $fundRequest)
                        <form method="POST" action="{{ route('fund-requests.approve', $fundRequest) }}">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('fund-requests.not-approve', $fundRequest) }}"
                              onsubmit="return confirm('Mark this request as not approved?');">
                            @csrf
                            <textarea name="comments" class="form-control mb-2" rows="2" placeholder="Reason (required)" required></textarea>
                            <button type="submit" class="btn btn-outline-danger w-100">Not Approve</button>
                        </form>
                    @endcan

                    @can('createApVoucher', $fundRequest)
                        <form method="POST" action="{{ route('fund-requests.ap-voucher', $fundRequest) }}">
                            @csrf
                            <label class="form-label small">APV No.</label>
                            <input type="text" name="apv_no" class="form-control mb-2" required>
                            <button type="submit" class="btn btn-primary w-100">Create AP Voucher</button>
                        </form>
                    @endcan

                    @can('provideCvNumber', $fundRequest)
                        <form method="POST" action="{{ route('fund-requests.cv-number', $fundRequest) }}">
                            @csrf
                            <label class="form-label small">CV No.</label>
                            <input type="text" name="cv_no" class="form-control mb-2" required>
                            <button type="submit" class="btn btn-primary w-100">Provide CV Number</button>
                        </form>
                    @endcan

                    @can('disburse', $fundRequest)
                        <form method="POST" action="{{ route('fund-requests.disburse', $fundRequest) }}">
                            @csrf
                            <label class="form-label small">Payment Mode</label>
                            <input type="text" name="payment_mode" class="form-control mb-2" placeholder="e.g. Check, Bank Transfer">
                            <label class="form-label small">Reference No.</label>
                            <input type="text" name="payment_reference" class="form-control mb-2">
                            <button type="submit" class="btn btn-primary w-100">Disburse &amp; Notify Requestor</button>
                        </form>
                    @endcan

                    @canany(['update', 'validateAsOe', 'validateAsStaff', 'approve', 'createApVoucher', 'provideCvNumber', 'disburse'], $fundRequest)
                    @else
                        <p class="text-muted mb-0">No action available at this stage for your role.</p>
                    @endcanany

                </div>
            </div>

            @if ($fundRequest->attachments->isNotEmpty() || auth()->user()->can('attach', $fundRequest))
                <div class="card mt-4">
                    <div class="card-header">Attachments</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($fundRequest->attachments as $attachment)
                            <li class="list-group-item small d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <a href="{{ route('fund-requests.attachments.download', [$fundRequest, $attachment]) }}">{{ $attachment->original_file_name }}</a>
                                    <div class="text-muted">
                                        {{ $attachment->humanSize() }} · {{ $attachment->uploader->full_name }} · {{ $attachment->uploaded_at->format('M d, Y') }}
                                    </div>
                                </div>
                                @can('attach', $fundRequest)
                                    <form method="POST" action="{{ route('fund-requests.attachments.destroy', [$fundRequest, $attachment]) }}"
                                          onsubmit="return confirm('Remove this attachment?');" class="mb-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                @endcan
                            </li>
                        @empty
                            <li class="list-group-item small text-muted">No attachments yet.</li>
                        @endforelse
                    </ul>

                    @can('attach', $fundRequest)
                        <div class="card-body border-top">
                            <form method="POST" action="{{ route('fund-requests.attachments.store', $fundRequest) }}" enctype="multipart/form-data">
                                @csrf
                                <label class="form-label small">Add files <span class="text-muted">(PDF, JPG, PNG, Word, Excel · up to 5 files, 5 MB each)</span></label>
                                <input type="file" name="files[]" class="form-control mb-2" multiple required
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Upload</button>
                            </form>
                        </div>
                    @endcan
                </div>
            @endif
        </div>
    </div>
@endsection
