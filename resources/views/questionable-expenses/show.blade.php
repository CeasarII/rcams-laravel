@extends('layouts.app')

@section('title', $qe->control_no)

@section('content')
    @php
        $item = $qe->sourceItem();
        $isRf = $qe->isRevolvingFund();
        $source = $isRf ? $item->expense : $item->liquidation;   // replenishment or liquidation
        $code = $qe->status->code;
        $closed = $code === 'CLOSED';
        $notValid = $qe->hard_copy_valid === false;

        // Process guide page 4: step => [label, owner, reached?]
        $order = ['WAITING_HARD_COPY', 'FOR_IR', 'FOR_OE_RECEIPT', 'FOR_SALARY_DEDUCTION', 'FOR_PROOF_OF_DEDUCTION', 'FOR_CLOSURE', 'CLOSED'];
        $at = array_search($code, $order, true);
        $steps = [
            ['Validate the file, now with hard copy', 'Accounting Staff', $qe->hard_copy_checked_at !== null],
            ['Keep the tag as Advances to Employee; generate the Incident Report', 'Accounting Staff', $qe->ir_no !== null],
            ['Receive the Incident Report', 'Approver / OE', $qe->ir_received_at !== null],
            ['Subject it to salary deduction', 'Crewing', $qe->deduction_at !== null],
            ['Secure proof of deduction (authority to deduct)', 'Crewing', $qe->proof_secured_at !== null],
            ['Close the QE', 'Accounting Staff', $closed],
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $qe->control_no }}</h1>
            <x-status-badge :status="$qe->status" class="fs-6" />
            <span class="text-muted ms-2 small">
                {{ $isRf ? 'Revolving fund replenishment' : 'Liquidation' }}
                @can('view', $source)
                    <a href="{{ $isRf ? route('revolving-fund-expenses.show', $source) : route('liquidations.show', $source) }}">{{ $source->control_no }}</a>
                @else
                    {{ $source->control_no }}
                @endcan
            </span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-header">Flagged Expense</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Requestor</dt>
                        <dd class="col-sm-8">{{ $qe->owner()->full_name }}</dd>

                        <dt class="col-sm-4">Supplier</dt>
                        <dd class="col-sm-8">{{ $item->supplier_name }}@if ($item->tin) &middot; TIN {{ $item->tin }}@endif</dd>

                        <dt class="col-sm-4">Purpose</dt>
                        <dd class="col-sm-8">{{ $item->purpose }}@if ($item->reference) &middot; Ref {{ $item->reference }}@endif</dd>

                        <dt class="col-sm-4">Nature of Cost</dt>
                        <dd class="col-sm-8">{{ $item->natureOfCost->label }}</dd>

                        <dt class="col-sm-4">Amount</dt>
                        <dd class="col-sm-8">
                            {{ number_format($item->gross_amount, 2) }}
                            <span class="text-muted small">
                                ({{ $item->vat_nature === 'VAT' ? 'VAT' : 'Non-VAT' }}; net {{ number_format($item->net_amount, 2) }}, input VAT {{ number_format($item->input_tax, 2) }})
                            </span>
                        </dd>

                        <dt class="col-sm-4">Tagged</dt>
                        <dd class="col-sm-8">
                            {{ $qe->taggedBy->full_name }} ({{ $qe->tagged_role === 'OE' ? 'Approver / OE' : 'Accounting Staff' }}),
                            {{ $qe->tagged_at->format('Y-m-d H:i') }}
                        </dd>

                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8">{{ $qe->reason }}</dd>

                        @if ($qe->hard_copy_checked_at)
                            <dt class="col-sm-4">Hard copy</dt>
                            <dd class="col-sm-8">
                                Received {{ $qe->hard_copy_received_on->format('Y-m-d') }} &mdash;
                                <strong>{{ $qe->hard_copy_valid ? 'valid' : 'not valid' }}</strong>
                                @if ($qe->hard_copy_remarks) &middot; {{ $qe->hard_copy_remarks }} @endif
                            </dd>
                        @endif

                        @if ($qe->ir_no)
                            <dt class="col-sm-4">Incident Report</dt>
                            <dd class="col-sm-8">
                                {{ $qe->ir_no }} ({{ $qe->ir_date->format('Y-m-d') }})
                                @if ($qe->ir_received_at) &middot; received {{ $qe->ir_received_at->format('Y-m-d') }} @endif
                            </dd>
                        @endif

                        @if ($qe->deduction_at)
                            <dt class="col-sm-4">Salary deduction</dt>
                            <dd class="col-sm-8">{{ number_format($qe->deduction_amount, 2) }} &middot; {{ $qe->deduction_at->format('Y-m-d') }}</dd>
                        @endif

                        @if ($closed)
                            <dt class="col-sm-4">Resolution</dt>
                            <dd class="col-sm-8">
                                {{ $qe->resolution === 'EXPENSE' ? 'Tag changed to Expense' : 'Settled by salary deduction' }}
                                &middot; closed {{ $qe->closed_at->format('Y-m-d') }}
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($qe->attachments->isNotEmpty() || auth()->user()->can('secureProof', $qe))
                <div class="card mb-4">
                    <div class="card-header">Proof of Deduction / Authority to Deduct</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($qe->attachments as $attachment)
                            <li class="list-group-item small d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('questionable-expenses.attachments.download', [$qe, $attachment]) }}">{{ $attachment->original_file_name }}</a>
                                    <span class="text-muted">&middot; {{ $attachment->uploader->full_name }} &middot; {{ $attachment->uploaded_at->format('Y-m-d') }}</span>
                                </div>
                                @can('secureProof', $qe)
                                    <form method="POST" action="{{ route('questionable-expenses.attachments.destroy', [$qe, $attachment]) }}"
                                          onsubmit="return confirm('Remove this file?');" class="mb-0">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                @endcan
                            </li>
                        @empty
                            <li class="list-group-item small text-muted">Nothing uploaded yet.</li>
                        @endforelse
                    </ul>
                    @can('secureProof', $qe)
                        <div class="card-body border-top">
                            <form method="POST" action="{{ route('questionable-expenses.attachments.store', $qe) }}" enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="files[]" class="form-control mb-2" multiple required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Upload</button>
                            </form>
                        </div>
                    @endcan
                </div>
            @endif

            <div class="card">
                <div class="card-header">History</div>
                <table class="table mb-0 small">
                    <thead><tr><th>When</th><th>Role</th><th>By</th><th>Action</th><th>Remarks</th></tr></thead>
                    <tbody>
                    @foreach ($qe->events as $event)
                        <tr>
                            <td class="text-nowrap">{{ $event->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $event->role->label }}</td>
                            <td>{{ $event->actor->full_name }}</td>
                            <td>{{ $event->action }}</td>
                            <td>{{ $event->comments }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            @if ($code === 'TAGGED')
                <div class="alert alert-warning small">
                    Tagged &mdash; Advances to Employee. The Accounting Head sets the tag status to
                    <strong>HC pending</strong> (replenishment step 6); then Accounting Staff can validate the hard copy.
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">Process</div>
                <ol class="list-group list-group-flush list-group-numbered small">
                    @foreach ($steps as $i => [$label, $owner, $done])
                        @php
                            // Steps 2-6 only apply once the hard copy was found not valid.
                            $skipped = $i > 0 && $i < 5 && $qe->hard_copy_valid === true;
                            $isCurrent = ! $closed && ! $skipped && ! $done && $at !== false && $at === $i;
                        @endphp
                        <li class="list-group-item {{ $isCurrent ? 'list-group-item-warning' : '' }} {{ $skipped ? 'text-muted' : '' }}">
                            @if ($done) <span class="text-success">&#10003;</span> @endif
                            <span @class(['text-decoration-line-through' => $skipped])>{{ $label }}</span>
                            <div class="text-muted">{{ $owner }}</div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="card">
                <div class="card-header">Actions</div>
                <div class="card-body d-grid gap-2">

                    @can('validateHardCopy', $qe)
                        <form method="POST" action="{{ route('questionable-expenses.validate-hard-copy', $qe) }}">
                            @csrf
                            <label class="form-label small">Hard copy received on</label>
                            <input type="date" name="hard_copy_received_on" class="form-control mb-2" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                            <label class="form-label small">Is the hard copy valid?</label>
                            <select name="hard_copy_valid" class="form-select mb-2" required>
                                <option value="">Select&hellip;</option>
                                <option value="1">Valid &mdash; change tag to Expense and close</option>
                                <option value="0">Not valid &mdash; keep as Advances to Employee</option>
                            </select>
                            <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Remarks (required if not valid)"></textarea>
                            <button type="submit" class="btn btn-primary w-100">Validate Hard Copy</button>
                        </form>
                    @endcan

                    @can('generateIr', $qe)
                        <form method="POST" action="{{ route('questionable-expenses.generate-ir', $qe) }}">
                            @csrf
                            <label class="form-label small">IR No.</label>
                            <input type="text" name="ir_no" class="form-control mb-2" required maxlength="50">
                            <label class="form-label small">IR Date</label>
                            <input type="date" name="ir_date" class="form-control mb-2" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                            <button type="submit" class="btn btn-primary w-100">Generate Incident Report</button>
                        </form>
                    @endcan

                    @can('receiveIr', $qe)
                        <form method="POST" action="{{ route('questionable-expenses.receive-ir', $qe) }}">
                            @csrf
                            <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-primary w-100">Receive the IR</button>
                        </form>
                    @endcan

                    @can('subjectToDeduction', $qe)
                        <form method="POST" action="{{ route('questionable-expenses.salary-deduction', $qe) }}">
                            @csrf
                            <p class="small mb-2">Deduction amount: <strong>{{ number_format($item->gross_amount, 2) }}</strong></p>
                            <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-primary w-100">Subject to Salary Deduction</button>
                        </form>
                    @endcan

                    @can('secureProof', $qe)
                        <form method="POST" action="{{ route('questionable-expenses.proof-of-deduction', $qe) }}">
                            @csrf
                            <p class="small text-muted mb-2">Upload the proof of deduction first, then confirm.</p>
                            <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-primary w-100">Proof of Deduction Secured</button>
                        </form>
                    @endcan

                    @can('close', $qe)
                        <form method="POST" action="{{ route('questionable-expenses.close', $qe) }}">
                            @csrf
                            <textarea name="remarks" class="form-control mb-2" rows="2" placeholder="Remarks (optional)"></textarea>
                            <button type="submit" class="btn btn-success w-100">Close the QE</button>
                        </form>
                    @endcan

                    @canany(['validateHardCopy', 'generateIr', 'receiveIr', 'subjectToDeduction', 'secureProof', 'close'], $qe)
                    @else
                        <p class="text-muted mb-0">No action available at this stage for your role.</p>
                    @endcanany
                </div>
            </div>
        </div>
    </div>
@endsection
