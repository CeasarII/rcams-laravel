<?php

namespace App\Http\Requests;

use App\Models\FundRequest;
use App\Models\FundRequestType;
use App\Models\RevolvingFund;
use App\Models\RevolvingFundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FundRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('REQUESTOR');
    }

    /**
     * Department and vessel come from the requestor's account, never from the
     * form: the department is always their own; Sea Base needs one of their
     * assigned vessels (a single assignment is filled in automatically); a
     * requestor with no assigned vessel can only file Shore Base.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();
        $assigned = $user->vessels()->pluck('vessels.id');

        $category = $assigned->isEmpty()
            ? FundRequest::CATEGORY_SHORE_BASE
            : $this->input('category');

        $vesselId = null;
        if ($category === FundRequest::CATEGORY_SEA_BASE) {
            $vesselId = $assigned->count() === 1 ? $assigned->first() : $this->input('vessel_id');
        }

        $this->merge([
            'department_id' => $user->department_id,
            'category' => $category,
            'vessel_id' => $vesselId,
            // Only a Revolving Fund request names a fund (the one it funds).
            'revolving_fund_id' => $this->isRevolvingFundType() ? $this->input('revolving_fund_id') : null,
        ]);
    }

    public function rules(): array
    {
        // The request's existing vessel stays valid when editing, even if the
        // assignment changed since it was saved.
        $allowedVessels = $this->user()->vessels()->pluck('vessels.id');
        $existing = $this->route('fund_request');
        if ($existing && $existing->vessel_id) {
            $allowedVessels->push($existing->vessel_id);
        }

        // Funds this user is custodian of (active), plus the one already on the request when editing.
        $allowedFunds = RevolvingFund::query()
            ->where('custodian_id', $this->user()->id)
            ->whereHas('status', fn ($q) => $q->where('code', RevolvingFundStatus::ACTIVE))
            ->pluck('id');
        if ($existing && $existing->revolving_fund_id) {
            $allowedFunds->push($existing->revolving_fund_id);
        }

        return [
            'revolving_fund_id' => [
                Rule::requiredIf(fn () => $this->isRevolvingFundType()),
                'nullable', 'exists:revolving_funds,id', Rule::in($allowedFunds->all()),
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'category' => ['required', Rule::in([
                FundRequest::CATEGORY_SHORE_BASE,
                FundRequest::CATEGORY_SEA_BASE,
            ])],
            // Sea Base requires a vessel — mirrors the DB CHECK constraint.
            'vessel_id' => ['nullable', 'required_if:category,' . FundRequest::CATEGORY_SEA_BASE, 'exists:vessels,id',
                Rule::in($allowedVessels->all())],
            'fund_request_type_id' => ['required', 'exists:fund_request_types,id'],
            'request_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:2000'],

            'details' => ['required', 'array', 'min:1'],
            'details.*.purpose' => ['required', 'string', 'max:255'],
            'details.*.description' => ['nullable', 'string', 'max:2000'],
            // amount_requested is still recalculated server-side from these
            // lines by FundRequest::recalculateAmount() — this only bounds
            // each line item.
            'details.*.amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }

    private function isRevolvingFundType(): bool
    {
        return FundRequestType::where('id', $this->input('fund_request_type_id'))->value('code') === FundRequestType::REVOLVING_FUND;
    }

    /** Funding may not take the fund past its limit. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fundId = $this->input('revolving_fund_id');

            if (! $fundId || $validator->errors()->has('revolving_fund_id') || $validator->errors()->has('details')) {
                return;
            }

            $fund = RevolvingFund::find($fundId);
            $total = round((float) collect($this->input('details', []))->sum(fn ($line) => (float) ($line['amount'] ?? 0)), 2);

            if ($fund && $total > $fund->headroom()) {
                $validator->errors()->add('details', sprintf(
                    'This would take %s over its limit (limit %s, balance %s) — the most you can request is %s.',
                    $fund->fund_name, number_format($fund->fund_limit, 2), number_format($fund->current_balance, 2), number_format($fund->headroom(), 2)
                ));
            }
        });
    }
}
