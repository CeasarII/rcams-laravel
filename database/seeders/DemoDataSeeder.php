<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\FundRequest;
use App\Models\FundRequestType;
use App\Models\RequestStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local/testing data: one company, departments, vessels, and one login per
 * role so every screen and workflow step can be tried by switching users.
 * Safe to re-run (matches on code/name/email). Every account uses the
 * password below — never run this against a real environment.
 *
 * Requires ProcessASeeder to have run first (it creates the roles).
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['code' => 'DEMO'],
            ['name' => 'Demo Shipping Corp', 'is_active' => true]
        );

        $departments = [];
        foreach ([
            'OPS' => 'Operations',
            'ACCT' => 'Accounting',
            'TRSY' => 'Treasury',
            'CREW' => 'Crewing',
        ] as $code => $name) {
            $departments[$code] = Department::updateOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }

        $types = [];
        foreach (['TANKER' => 'Tanker', 'TUG' => 'Tug Boat', 'CARGO' => 'Cargo Ship'] as $code => $label) {
            $types[$code] = VesselType::updateOrCreate(
                ['code' => $code],
                ['label' => $label, 'is_active' => true]
            );
        }

        $alpha = Vessel::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'MT Demo Alpha'],
            ['vessel_type_id' => $types['TANKER']->id, 'imo_no' => '9000001', 'is_active' => true]
        );
        $bravo = Vessel::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'MV Demo Bravo'],
            ['vessel_type_id' => $types['CARGO']->id, 'imo_no' => '9000002', 'is_active' => true]
        );
        Vessel::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'TB Demo Charlie'],
            ['vessel_type_id' => $types['TUG']->id, 'imo_no' => null, 'is_active' => true]
        );

        $accounts = [
            ['EMP-0001', 'System Admin',       'admin@cams.test',     'OPS',  ['ADMIN']],
            ['EMP-0002', 'Rita Requestor',     'requestor@cams.test', 'CREW', ['REQUESTOR']],
            ['EMP-0003', 'Oscar Engineer',     'oe@cams.test',        'OPS',  ['OE']],
            ['EMP-0004', 'Sam Staff',          'staff@cams.test',     'ACCT', ['ACCT_STAFF']],
            ['EMP-0005', 'Helen Head',         'head@cams.test',      'ACCT', ['ACCT_HEAD']],
            ['EMP-0006', 'Tina Treasury',      'treasury@cams.test',  'TRSY', ['TREASURY']],
            ['EMP-0007', 'Manny Multi-Role',   'multi@cams.test',     'OPS',  ['REQUESTOR', 'OE']],
            ['EMP-0008', 'Carla Crewing',      'crewing@cams.test',   'CREW', ['CREWING']],
        ];

        foreach ($accounts as [$employeeNo, $fullName, $email, $deptCode, $roleCodes]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'employee_no' => $employeeNo,
                    'full_name' => $fullName,
                    'password' => Hash::make(self::PASSWORD),
                    'department_id' => $departments[$deptCode]->id,
                    'company_id' => $company->id,
                    'is_active' => true,
                ]
            );

            $user->roles()->sync(Role::whereIn('code', $roleCodes)->pluck('id'));

            // Requestors file Sea Base requests for their assigned vessel(s):
            // requestor@ gets exactly one (auto-filled), so Sea Base is the default.
            if ($email === 'requestor@cams.test') {
                $user->vessels()->sync([$alpha->id]);
            }

            // OEs validate Sea Base requests for their vessels and Shore Base
            // requests for their departments; an unassigned OE can't act.
            if (in_array('OE', $roleCodes, true)) {
                $user->vessels()->sync([$alpha->id, $bravo->id]);
                $user->departments()->sync([$departments['CREW']->id, $departments['OPS']->id]);
            }
        }

        // A disbursed Cash Advance (Completed) for requestor@cams.test, so a
        // liquidation can be started right away. The control number is outside
        // the CA-<year>-nnnnn sequence on purpose.
        $requestor = User::where('email', 'requestor@cams.test')->first();

        if ($requestor) {
            $cashAdvance = FundRequest::updateOrCreate(
                ['control_no' => 'CA-DEMO-00001'],
                [
                    'requestor_id' => $requestor->id,
                    'department_id' => $requestor->department_id,
                    'company_id' => $company->id,
                    'category' => FundRequest::CATEGORY_SEA_BASE,
                    'vessel_id' => $alpha->id,
                    'fund_request_type_id' => FundRequestType::where('code', FundRequestType::CASH_ADVANCE)->firstOrFail()->id,
                    'amount_requested' => 10000,
                    'request_date' => now()->toDateString(),
                    'purpose' => 'Voyage expenses — demo cash advance',
                    'request_status_id' => RequestStatus::where('code', RequestStatus::COMPLETED)->firstOrFail()->id,
                    'submitted_at' => now(),
                    'completed_at' => now(),
                ]
            );

            if ($cashAdvance->details()->count() === 0) {
                $cashAdvance->details()->create(['purpose' => 'Voyage expenses', 'description' => 'Demo', 'amount' => 10000]);
            }
        }

        // A funded revolving fund with an open voyage, custodian requestor@cams.test, so voyage
        // expenses can be prepared right away. Re-running never changes an existing balance.
        if ($requestor) {
            $fund = \App\Models\RevolvingFund::firstOrCreate(
                ['fund_code' => 'RF-ALPHA'],
                [
                    'fund_name' => 'MT Demo Alpha Revolving Fund',
                    'vessel_id' => $alpha->id,
                    'company_id' => $company->id,
                    'department_id' => $requestor->department_id,
                    'custodian_id' => $requestor->id,
                    'fund_limit' => 50000,
                    'current_balance' => 0,
                    'status_id' => \App\Models\RevolvingFundStatus::where('code', \App\Models\RevolvingFundStatus::ACTIVE)->firstOrFail()->id,
                ]
            );

            if ($fund->wasRecentlyCreated) {
                app(\App\Services\RevolvingFundLedger::class)->post($fund, \App\Models\RevolvingFundTransactionType::FUNDING, 50000, [
                    'reference_no' => 'OPENING BALANCE',
                    'description' => 'Demo opening balance',
                ]);

                \App\Models\RevolvingFundVoyage::create([
                    'revolving_fund_id' => $fund->id,
                    'voyage_no' => 'VOY-001',
                    'start_date' => now()->toDateString(),
                    'beginning_balance' => $fund->current_balance,
                    'status_id' => \App\Models\RevolvingFundVoyageStatus::where('code', \App\Models\RevolvingFundVoyageStatus::OPEN)->firstOrFail()->id,
                ]);
            }
        }
    }
}
