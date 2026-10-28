<?php

namespace App\Http\Controllers;

use App\Models\CashAdvanceLiquidation;
use App\Models\LiquidationItem;
use App\Services\LiquidationWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Each action: authorize via CashAdvanceLiquidationPolicy, delegate to
 * LiquidationWorkflowService (which re-checks status and transacts).
 * ValidationExceptions from the service redirect back with $errors.
 */
class LiquidationWorkflowController extends Controller
{
    public function __construct(private LiquidationWorkflowService $workflow)
    {
    }

    public function submit(CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('update', $liquidation);
        $this->workflow->submit($liquidation, Auth::user());

        return back()->with('status', "{$liquidation->control_no} submitted for OE validation.");
    }

    /** OE: "Liquidation okay?" = Yes */
    public function validateOe(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('validateAsOe', $liquidation);
        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->validateByOe($liquidation, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', "{$liquidation->control_no} validated — sent to Accounting for verification.");
    }

    /** OE: "Liquidation okay?" = No */
    public function returnForEdit(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('validateAsOe', $liquidation);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->returnForEdit($liquidation, Auth::user(), $data['comments']);

        return back()->with('status', "{$liquidation->control_no} returned to the Requestor for editing.");
    }

    /** Staff: "Verification okay?" = Yes */
    public function verify(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('verifyAsStaff', $liquidation);
        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $liquidation = $this->workflow->verifyByStaff($liquidation, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', (float) $liquidation->amount_to_reimburse > 0
            ? "{$liquidation->control_no} verified — an amount is payable to the requestor; prepare the AP voucher."
            : "{$liquidation->control_no} verified — nothing payable to the requestor; sent to the Accounting Head to close.");
    }

    /** Staff: "Verification okay?" = No */
    public function returnForRevision(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('verifyAsStaff', $liquidation);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->returnForRevision($liquidation, Auth::user(), $data['comments']);

        return back()->with('status', "{$liquidation->control_no} returned to the Requestor for revision.");
    }

    public function prepareApv(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('prepareApv', $liquidation);
        $data = $request->validate([
            'apv_no' => ['required', 'string', 'max:30', 'unique:liquidation_vouchers,apv_no,'.($liquidation->voucher?->id ?? 'NULL')],
        ]);

        $this->workflow->prepareApv($liquidation, Auth::user(), $data['apv_no']);

        return back()->with('status', "APV {$data['apv_no']} prepared — sent to the Accounting Head for review.");
    }

    /** Head: "Review okay?" = Yes */
    public function approveApv(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('reviewApv', $liquidation);
        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        $this->workflow->approveApv($liquidation, Auth::user(), $data['comments'] ?? null);

        return back()->with('status', 'APV approved — sent to Treasury.');
    }

    /** Head: "Review okay?" = No */
    public function rejectApv(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('reviewApv', $liquidation);
        $data = $request->validate(['comments' => ['required', 'string', 'max:2000']]);

        $this->workflow->rejectApv($liquidation, Auth::user(), $data['comments']);

        return back()->with('status', 'APV sent back to Accounting Staff.');
    }

    public function close(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('close', $liquidation);
        $data = $request->validate([
            'cash_return_reference' => ['nullable', 'string', 'max:100'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->workflow->closeLiquidation($liquidation, Auth::user(), $data['cash_return_reference'] ?? null, $data['comments'] ?? null);

        return back()->with('status', "{$liquidation->control_no} processed and closed.");
    }

    public function reimburse(Request $request, CashAdvanceLiquidation $liquidation)
    {
        $this->authorize('reimburse', $liquidation);
        $data = $request->validate([
            'cv_no' => ['required', 'string', 'max:30', 'unique:liquidation_vouchers,cv_no'],
            'payment_mode' => ['nullable', 'string', 'max:30'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->workflow->processReimbursement(
            $liquidation, Auth::user(), $data['cv_no'], $data['payment_mode'] ?? null, $data['payment_reference'] ?? null
        );

        return back()->with('status', "Reimbursement processed — {$liquidation->control_no} is closed and the Requestor was notified.");
    }

    // ---- Questionable Expense tagging ---------------------------------

    public function tag(Request $request, CashAdvanceLiquidation $liquidation, LiquidationItem $item)
    {
        $this->authorize('tagQuestionable', $liquidation);
        abort_unless($item->liquidation_id === $liquidation->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $qe = $this->workflow->tagQuestionable($item, Auth::user(), $data['reason']);

        return back()->with('status', "Item tagged as Questionable Expense {$qe->control_no} — waiting for hard copy.");
    }

    public function untag(CashAdvanceLiquidation $liquidation, LiquidationItem $item)
    {
        $this->authorize('tagQuestionable', $liquidation);
        abort_unless($item->liquidation_id === $liquidation->id, 404);

        $this->workflow->untagQuestionable($item, Auth::user());

        return back()->with('status', 'Questionable tag removed.');
    }
}
