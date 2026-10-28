<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Reference data for Cash Advance Liquidation and Questionable Expense. Safe to re-run. */
class LiquidationSeeder extends Seeder
{
    public function run(): void
    {
        $this->statuses('liquidation_statuses', [
            'DRAFT' => 'Draft',
            'FOR_OE_VALIDATION' => 'For OE Validation',
            'RETURNED_FOR_EDIT' => 'Returned for Edit',
            'FOR_STAFF_VERIFICATION' => 'For Accounting Verification',
            'RETURNED_FOR_REVISION' => 'Returned for Revision',
            'FOR_APV_PREPARATION' => 'For APV Preparation',
            'FOR_APV_REVIEW' => 'For APV Review',
            'FOR_HEAD_CLOSURE' => 'For Closure',
            'FOR_REIMBURSEMENT' => 'For Reimbursement',
            'CLOSED' => 'Closed',
        ]);

        $this->statuses('liquidation_item_statuses', [
            'EXPENSE' => 'Expense',
            'QUESTIONABLE' => 'Questionable (Advances to Employee)',
            'SETTLED_BY_DEDUCTION' => 'Settled by Salary Deduction',
        ]);

        $this->statuses('questionable_expense_statuses', [
            'TAGGED' => 'Tagged (Advances to Employee)',
            'WAITING_HARD_COPY' => 'Waiting for Hard Copy',
            'FOR_IR' => 'For Incident Report',
            'FOR_OE_RECEIPT' => 'For OE Receipt of IR',
            'FOR_SALARY_DEDUCTION' => 'For Salary Deduction',
            'FOR_PROOF_OF_DEDUCTION' => 'For Proof of Deduction',
            'FOR_CLOSURE' => 'For Closure',
            'CLOSED' => 'Closed',
        ]);

        // Starting set — Admin maintains these under Master Data.
        $this->lookups('nature_of_costs', [
            'FUEL' => 'Fuel and Lubricants',
            'PROVISIONS' => 'Food and Provisions',
            'SUPPLIES' => 'Ship Supplies',
            'REPAIRS' => 'Repairs and Maintenance',
            'PORT' => 'Port and Harbor Charges',
            'TRANSPO' => 'Transportation and Travel',
            'COMMS' => 'Communication',
            'PERMITS' => 'Permits, Licenses and Government Fees',
            'MISC' => 'Miscellaneous',
        ]);

        $this->lookups('document_types', [
            'OR' => 'Official Receipt',
            'SI' => 'Sales Invoice',
            'CI' => 'Cash Invoice',
            'BS' => 'Billing Statement',
            'AR' => 'Acknowledgment Receipt',
            'DR' => 'Delivery Receipt',
            'OTHER' => 'Other Supporting Document',
        ]);
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

    private function lookups(string $table, array $rows): void
    {
        foreach ($rows as $code => $label) {
            DB::table($table)->updateOrInsert(
                ['code' => $code],
                ['label' => $label, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
