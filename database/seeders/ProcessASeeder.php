<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcessASeeder extends Seeder
{
    public function run(): void
    {
        $this->roles();
        $this->fundRequestTypes();
        $this->requestStatuses();
        $this->approvalStatuses();
    }

    private function roles(): void
    {
        $rows = [
            ['code' => 'REQUESTOR', 'label' => 'Requestor'],
            ['code' => 'OE', 'label' => 'Approver / OE'],
            ['code' => 'ACCT_STAFF', 'label' => 'Accounting Staff'],
            ['code' => 'ACCT_HEAD', 'label' => 'Accounting Head'],
            ['code' => 'TREASURY', 'label' => 'Treasury'],
            ['code' => 'CREWING', 'label' => 'Crewing'],
            ['code' => 'ADMIN', 'label' => 'System Admin'],
        ];

        foreach ($rows as $row) {
            DB::table('roles')->updateOrInsert(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function fundRequestTypes(): void
    {
        $rows = [
            ['code' => 'CASH_ADVANCE', 'label' => 'Cash Advance', 'sort_order' => 1],
            ['code' => 'REVOLVING_FUND', 'label' => 'Revolving Fund', 'sort_order' => 2],
        ];

        foreach ($rows as $row) {
            DB::table('fund_request_types')->updateOrInsert(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function requestStatuses(): void
    {
        // "Who holds the record" states — Phase 1 §5.
        $rows = [
            ['code' => 'DRAFT', 'label' => 'Draft', 'sort_order' => 1],
            ['code' => 'RETURNED_TO_REQUESTOR', 'label' => 'Returned to Requestor', 'sort_order' => 2],
            ['code' => 'FOR_OE_VALIDATION', 'label' => 'For OE Validation', 'sort_order' => 3],
            ['code' => 'RETURNED_TO_OE', 'label' => 'Returned to OE', 'sort_order' => 4],
            ['code' => 'FOR_ACCT_VALIDATION', 'label' => 'For Accounting Validation', 'sort_order' => 5],
            ['code' => 'RETURNED_TO_STAFF', 'label' => 'Returned to Staff', 'sort_order' => 6],
            ['code' => 'FOR_HEAD_APPROVAL', 'label' => 'For Head Approval', 'sort_order' => 7],
            ['code' => 'FOR_AP_VOUCHER', 'label' => 'For AP Voucher', 'sort_order' => 8],
            ['code' => 'FOR_CV_NUMBER', 'label' => 'For CV Number', 'sort_order' => 9],
            ['code' => 'FOR_DISBURSEMENT', 'label' => 'For Disbursement', 'sort_order' => 10],
            ['code' => 'COMPLETED', 'label' => 'Completed', 'sort_order' => 11],
            ['code' => 'CANCELLED', 'label' => 'Cancelled', 'sort_order' => 12],
        ];

        foreach ($rows as $row) {
            DB::table('request_statuses')->updateOrInsert(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function approvalStatuses(): void
    {
        $rows = [
            ['code' => 'SUBMITTED', 'label' => 'Submitted', 'sort_order' => 1],
            ['code' => 'VALIDATED', 'label' => 'Validated', 'sort_order' => 2],
            ['code' => 'RETURNED', 'label' => 'Returned', 'sort_order' => 3],
            ['code' => 'APPROVED', 'label' => 'Approved', 'sort_order' => 4],
            ['code' => 'NOT_APPROVED', 'label' => 'Not Approved', 'sort_order' => 5],
            ['code' => 'APV_CREATED', 'label' => 'AP Voucher Created', 'sort_order' => 6],
            ['code' => 'CV_CREATED', 'label' => 'CV Number Provided', 'sort_order' => 7],
            ['code' => 'DISBURSED', 'label' => 'Disbursed', 'sort_order' => 8],
            ['code' => 'CANCELLED', 'label' => 'Cancelled', 'sort_order' => 9],
            // Cash Advance Liquidation / Questionable Expense
            ['code' => 'REIMBURSED', 'label' => 'Reimbursed', 'sort_order' => 10],
            ['code' => 'CLOSED', 'label' => 'Closed', 'sort_order' => 11],
            ['code' => 'QE_TAGGED', 'label' => 'Tagged as Questionable Expense', 'sort_order' => 12],
            ['code' => 'QE_UNTAGGED', 'label' => 'Questionable Tag Removed', 'sort_order' => 13],
            ['code' => 'HC_PENDING_SET', 'label' => 'Tag Status Set (HC Pending)', 'sort_order' => 14],
        ];

        foreach ($rows as $row) {
            DB::table('approval_statuses')->updateOrInsert(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
