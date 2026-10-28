<?php

namespace App\Http\Controllers;

use App\Models\FundRequest;
use App\Services\FundRequestWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Every action here: authorize via FundRequestPolicy, delegate to
 * FundRequestWorkflowService (which re-checks status and transacts),
 * then redirect back to the request with a status message. Validation
 * failures thrown by the service (wrong status, not scoped, etc.) are
 * caught by Laravel's default exception handler and redirected back
 * with $errors, same as a form request failure.
 */
class FundRequestWorkflowController extends Controller
{
    public function __construct(private FundRequestWorkflowService $workflow)
    {
    }

    public function submit(FundRequest $fundRequest)
    {
        $this->authorize('update', $fundRequest);

        $this->workflow->submit($fundRequest, Auth::user());

        return back()->with('status', "{$fundRequest->control_no} submitted for OE validation.");
    }

    public function validateOe(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('validateAsOe', $fundRequest);

        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->validateByOe($fundRequest, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', "{$fundRequest->control_no} validated — sent to Accounting.");
    }

    public function returnToRequestor(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('validateAsOe', $fundRequest);

        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->returnToRequestor($fundRequest, Auth::user(), $data['comments']);

        return back()->with('status', "{$fundRequest->control_no} returned to the Requestor.");
    }

    public function validateStaff(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('validateAsStaff', $fundRequest);

        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->validateByStaff($fundRequest, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', "{$fundRequest->control_no} validated — sent to the Accounting Head.");
    }

    public function returnToOe(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('validateAsStaff', $fundRequest);

        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->returnToOe($fundRequest, Auth::user(), $data['comments']);

        return back()->with('status', "{$fundRequest->control_no} returned to the Approver/OE.");
    }

    public function approve(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('approve', $fundRequest);

        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->approve($fundRequest, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', "{$fundRequest->control_no} approved.");
    }

    public function notApprove(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('approve', $fundRequest);

        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->notApprove($fundRequest, Auth::user(), $data['comments']);

        return back()->with('status', "{$fundRequest->control_no} not approved — returned to Accounting Staff.");
    }

    public function createApVoucher(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('createApVoucher', $fundRequest);

        $data = $request->validate([
            'apv_no' => ['required', 'string', 'max:30', 'unique:ap_vouchers,apv_no'],
            'apv_date' => ['nullable', 'date'],
        ]);

        $this->workflow->createApVoucher($fundRequest, Auth::user(), $data['apv_no'], $data['apv_date'] ?? null);

        return back()->with('status', "AP Voucher {$data['apv_no']} created for {$fundRequest->control_no}.");
    }

    public function provideCvNumber(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('provideCvNumber', $fundRequest);

        $data = $request->validate([
            'cv_no' => ['required', 'string', 'max:30', 'unique:check_vouchers,cv_no'],
        ]);

        $this->workflow->provideCvNumber($fundRequest, Auth::user(), $data['cv_no']);

        return back()->with('status', "CV Number {$data['cv_no']} recorded for {$fundRequest->control_no}.");
    }

    public function disburse(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('disburse', $fundRequest);

        $data = $request->validate([
            'payment_mode' => ['nullable', 'string', 'max:30'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->workflow->disburse($fundRequest, Auth::user(), $data['payment_mode'] ?? null, $data['payment_reference'] ?? null);

        return back()->with('status', "{$fundRequest->control_no} disbursed. Requestor notified.");
    }

    public function cancel(FundRequest $fundRequest, Request $request)
    {
        $this->authorize('cancel', $fundRequest);

        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->cancel($fundRequest, Auth::user(), $data['comments']);

        return redirect()
            ->route('fund-requests.index')
            ->with('status', "{$fundRequest->control_no} cancelled.");
    }
}
