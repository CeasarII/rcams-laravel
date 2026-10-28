<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\RevolvingFund;
use App\Models\RevolvingFundStatus;
use App\Models\RevolvingFundTransactionType;
use App\Models\User;
use App\Models\Vessel;
use App\Services\RevolvingFundLedger;
use App\Support\LiquidationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Revolving funds: Admin sets them up; custodians and the process roles see
 * balances, voyages and the ledger. The balance only ever changes through
 * RevolvingFundLedger — there is no balance field on these forms.
 */
class RevolvingFundController extends Controller
{
    public function __construct(private RevolvingFundLedger $ledger)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', RevolvingFund::class);

        $user = Auth::user();
        $search = trim((string) $request->query('q', ''));

        $query = RevolvingFund::query()->with(['vessel', 'department', 'custodian', 'status'])->orderBy('fund_name');

        if (! LiquidationAccess::seesAll(LiquidationAccess::roles($user))) {
            $query->where('custodian_id', $user->id);
        }

        if ($search !== '') {
            $query->where(fn ($q) => $q->where('fund_code', 'like', "%{$search}%")->orWhere('fund_name', 'like', "%{$search}%"));
        }

        return view('revolving-funds.index', ['funds' => $query->paginate(20)->withQueryString(), 'search' => $search]);
    }

    public function show(RevolvingFund $revolvingFund)
    {
        $this->authorize('view', $revolvingFund);

        $revolvingFund->load(['vessel', 'company', 'department', 'custodian', 'status', 'voyages.status']);

        return view('revolving-funds.show', [
            'fund' => $revolvingFund,
            'openVoyage' => $revolvingFund->voyages->first(fn ($v) => $v->isOpen()),
            'transactions' => $revolvingFund->transactions()->with(['type', 'fundRequest', 'expense'])->limit(50)->get(),
            'expenses' => $revolvingFund->expenses()->with(['status', 'preparer', 'voyage'])
                ->orderByDesc('revolving_fund_expenses.id')->limit(20)->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', RevolvingFund::class);

        return view('revolving-funds.form', $this->formData(null));
    }

    public function store(Request $request)
    {
        $this->authorize('create', RevolvingFund::class);

        $data = $request->validate($this->rules($request, null) + [
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
        $this->assertVesselMatchesDepartment($data);

        $opening = round((float) ($data['opening_balance'] ?? 0), 2);
        if ($opening > (float) $data['fund_limit']) {
            throw ValidationException::withMessages(['opening_balance' => 'The opening balance cannot exceed the fund limit.']);
        }

        $fund = DB::transaction(function () use ($data, $opening) {
            $fund = RevolvingFund::create([
                'fund_code' => $data['fund_code'],
                'fund_name' => $data['fund_name'],
                'vessel_id' => $data['vessel_id'] ?? null,
                'company_id' => Department::findOrFail($data['department_id'])->company_id,
                'department_id' => $data['department_id'],
                'custodian_id' => $data['custodian_id'],
                'fund_limit' => $data['fund_limit'],
                'current_balance' => 0,
                'status_id' => RevolvingFundStatus::where('code', RevolvingFundStatus::ACTIVE)->firstOrFail()->id,
            ]);

            if ($opening > 0) {
                $this->ledger->post($fund, RevolvingFundTransactionType::FUNDING, $opening, [
                    'reference_no' => 'OPENING BALANCE',
                    'description' => 'Opening balance recorded when the fund was set up',
                    'posted_by' => Auth::id(),
                ]);
            }

            return $fund;
        });

        return redirect()->route('revolving-funds.show', $fund)->with('status', "{$fund->fund_code} created.");
    }

    public function edit(RevolvingFund $revolvingFund)
    {
        $this->authorize('update', $revolvingFund);

        return view('revolving-funds.form', $this->formData($revolvingFund));
    }

    public function update(Request $request, RevolvingFund $revolvingFund)
    {
        $this->authorize('update', $revolvingFund);

        $data = $request->validate($this->rules($request, $revolvingFund) + [
            'status_id' => ['required', 'exists:revolving_fund_statuses,id'],
        ]);
        $this->assertVesselMatchesDepartment($data);

        if ((float) $data['fund_limit'] < (float) $revolvingFund->current_balance) {
            throw ValidationException::withMessages(['fund_limit' => 'The limit cannot be below the current balance ('.number_format($revolvingFund->current_balance, 2).').']);
        }

        $revolvingFund->update([
            'fund_code' => $data['fund_code'],
            'fund_name' => $data['fund_name'],
            'vessel_id' => $data['vessel_id'] ?? null,
            'company_id' => Department::findOrFail($data['department_id'])->company_id,
            'department_id' => $data['department_id'],
            'custodian_id' => $data['custodian_id'],
            'fund_limit' => $data['fund_limit'],
            'status_id' => $data['status_id'],
        ]);

        return redirect()->route('revolving-funds.show', $revolvingFund)->with('status', 'Revolving fund saved.');
    }

    private function rules(Request $request, ?RevolvingFund $fund): array
    {
        if ($request->filled('fund_code')) {
            $request->merge(['fund_code' => strtoupper(trim($request->input('fund_code')))]);
        }

        return [
            'fund_code' => ['required', 'string', 'max:30', Rule::unique('revolving_funds', 'fund_code')->ignore($fund?->id)],
            'fund_name' => ['required', 'string', 'max:150'],
            'department_id' => ['required', 'exists:departments,id'],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            // The custodian is a Requestor — the person who prepares the fund's voyage expenses.
            'custodian_id' => ['required', Rule::exists('users', 'id')->where('is_active', true), function ($attribute, $value, $fail) {
                if (! User::find($value)?->hasRole('REQUESTOR')) {
                    $fail('The custodian must hold the Requestor role.');
                }
            }],
            'fund_limit' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }

    /** A vessel fund's vessel must belong to the same company as its department. */
    private function assertVesselMatchesDepartment(array $data): void
    {
        if (empty($data['vessel_id'])) {
            return;
        }

        if (Vessel::findOrFail($data['vessel_id'])->company_id !== Department::findOrFail($data['department_id'])->company_id) {
            throw ValidationException::withMessages(['vessel_id' => 'The vessel belongs to a different company than the chosen department.']);
        }
    }

    private function formData(?RevolvingFund $fund): array
    {
        return [
            'fund' => $fund,
            'departments' => Department::with('company')->where('is_active', true)
                ->when($fund, fn ($q) => $q->orWhere('id', $fund->department_id))->orderBy('name')->get(),
            'vessels' => Vessel::where('is_active', true)
                ->when($fund?->vessel_id, fn ($q) => $q->orWhere('id', $fund->vessel_id))->orderBy('name')->get(),
            'custodians' => User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('code', 'REQUESTOR'))
                ->when($fund, fn ($q) => $q->orWhere('id', $fund->custodian_id))->orderBy('full_name')->get(),
            'statuses' => RevolvingFundStatus::orderBy('sort_order')->get(),
        ];
    }
}
