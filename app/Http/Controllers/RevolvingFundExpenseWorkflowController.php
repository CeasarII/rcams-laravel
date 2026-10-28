<?php

namespace App\Http\Controllers;

use App\Models\RevolvingFundExpense;
use App\Models\RevolvingFundExpenseItem;
use App\Services\RevolvingFundExpenseWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Each action: authorize via RevolvingFundExpensePolicy, delegate to
 * RevolvingFundExpenseWorkflowService (which re-checks status and transacts).
 */
class RevolvingFundExpenseWorkflowController extends Controller
{
    public function __construct(private RevolvingFundExpenseWorkflowService $workflow)
    {
    }

    public function submit(RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('submit', $revolvingFundExpense);
        $this->workflow->submit($revolvingFundExpense, Auth::user());

        return back()->with('status', "{$revolvingFundExpense->control_no} submitted for OE validation.");
    }

    /** Step 2 — valid */
    public function validateOe(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('validateAsOe', $revolvingFundExpense);
        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->validateByOe($revolvingFundExpense, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', "{$revolvingFundExpense->control_no} validated — sent to Accounting.");
    }

    /** Step 2 — not valid */
    public function returnToRequestor(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('returnToRequestor', $revolvingFundExpense);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->returnToRequestor($revolvingFundExpense, Auth::user(), $data['comments']);

        return back()->with('status', "{$revolvingFundExpense->control_no} returned to the Requestor to revise.");
    }

    /** Step 3 — valid */
    public function validateStaff(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('validateAsStaff', $revolvingFundExpense);
        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->validateByStaff($revolvingFundExpense, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', "{$revolvingFundExpense->control_no} validated — sent to the Accounting Head.");
    }

    /** Step 3 — not valid */
    public function returnToOe(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('validateAsStaff', $revolvingFundExpense);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->returnToOe($revolvingFundExpense, Auth::user(), $data['comments']);

        return back()->with('status', "{$revolvingFundExpense->control_no} returned to the Approver/OE to revise.");
    }

    /** Step 4 — approved */
    public function approve(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('decide', $revolvingFundExpense);
        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->approve($revolvingFundExpense, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', "{$revolvingFundExpense->control_no} approved — Accounting Staff creates the AP voucher.");
    }

    /** Step 4 — not approved */
    public function notApprove(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('decide', $revolvingFundExpense);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->notApprove($revolvingFundExpense, Auth::user(), $data['comments']);

        return back()->with('status', "{$revolvingFundExpense->control_no} sent back to Accounting Staff.");
    }

    /** Step 5 */
    public function createApv(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('createApv', $revolvingFundExpense);
        $data = $request->validate(['apv_no' => ['required', 'string', 'max:30', 'unique:revolving_fund_vouchers,apv_no']]);

        $expense = $this->workflow->createApVoucher($revolvingFundExpense, Auth::user(), $data['apv_no']);

        return back()->with('status', $expense->status->code === 'FOR_HC_TAG'
            ? "APV {$data['apv_no']} created — the Accounting Head sets the HC pending tag on the questionable items."
            : "APV {$data['apv_no']} created — sent to Treasury for the CV number.");
    }

    /** Step 6 */
    public function setHcPending(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('setHcPending', $revolvingFundExpense);
        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->setHcPending($revolvingFundExpense, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', 'Tag status set to HC pending — sent to Treasury.');
    }

    /** Step 7 */
    public function provideCv(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('provideCv', $revolvingFundExpense);
        $data = $request->validate(['cv_no' => ['required', 'string', 'max:30', 'unique:revolving_fund_vouchers,cv_no']]);

        $this->workflow->provideCvNumber($revolvingFundExpense, Auth::user(), $data['cv_no']);

        return back()->with('status', "CV {$data['cv_no']} recorded — ready for disbursement.");
    }

    /** Steps 8-9 */
    public function disburse(Request $request, RevolvingFundExpense $revolvingFundExpense)
    {
        $this->authorize('disburse', $revolvingFundExpense);
        $data = $request->validate([
            'payment_mode' => ['nullable', 'string', 'max:30'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->workflow->disburse($revolvingFundExpense, Auth::user(), $data['payment_mode'] ?? null, $data['payment_reference'] ?? null);

        return back()->with('status', 'Disbursed — the revolving fund was replenished and the Requestor was notified.');
    }

    // ---- Questionable Expense tagging (steps 2 and 3) -----------------

    public function tag(Request $request, RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item)
    {
        $this->authorize('tagQuestionable', $revolvingFundExpense);
        abort_unless($item->expense_id === $revolvingFundExpense->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $qe = $this->workflow->tagQuestionable($item, Auth::user(), $data['reason']);

        return back()->with('status', "Item tagged as Questionable Expense {$qe->control_no} — Advances to Employee, HC pending.");
    }

    public function untag(RevolvingFundExpense $revolvingFundExpense, RevolvingFundExpenseItem $item)
    {
        $this->authorize('tagQuestionable', $revolvingFundExpense);
        abort_unless($item->expense_id === $revolvingFundExpense->id, 404);

        $this->workflow->untagQuestionable($item, Auth::user());

        return back()->with('status', 'Questionable tag removed.');
    }
}
