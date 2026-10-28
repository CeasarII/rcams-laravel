<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Reference data for the Revolving Fund module. Safe to re-run. */
class RevolvingFundSeeder extends Seeder
{
    public function run(): void
    {
        $this->statuses('revolving_fund_statuses', ['ACTIVE' => 'Active', 'CLOSED' => 'Closed']);
        $this->statuses('revolving_fund_voyage_statuses', ['OPEN' => 'Open', 'CLOSED' => 'Closed']);

        $this->statuses('revolving_fund_expense_statuses', [
            'DRAFT' => 'Draft',
            'FOR_OE_VALIDATION' => 'For OE Validation',
            'RETURNED_TO_REQUESTOR' => 'Returned to Requestor',
            'FOR_STAFF_VALIDATION' => 'For Accounting Validation',
            'RETURNED_TO_OE' => 'Returned to Approver/OE',
            'FOR_HEAD_APPROVAL' => 'For Head Approval',
            'RETURNED_TO_STAFF' => 'Returned to Accounting Staff',
            'FOR_AP_VOUCHER' => 'For AP Voucher',
            'FOR_HC_TAG' => 'For HC Pending Tag',
            'FOR_CV_NUMBER' => 'For CV Number',
            'FOR_DISBURSEMENT' => 'For Disbursement',
            'COMPLETED' => 'Completed',
        ]);

        $order = 1;
        foreach (['FUNDING' => ['Funding', 1], 'EXPENSE' => ['Voyage Expenses', -1], 'REPLENISHMENT' => ['Replenishment', 1]] as $code => [$label, $sign]) {
            DB::table('revolving_fund_transaction_types')->updateOrInsert(
                ['code' => $code],
                ['label' => $label, 'sign' => $sign, 'sort_order' => $order++, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function statuses(string $table, array $rows): void
    {
        $order = 1;
        foreach ($rows as $code => $label) {
            DB::table($table)->updateOrInsert(
                ['code' => $code],
                ['label' => $label, 'sort_order' => $order++, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
