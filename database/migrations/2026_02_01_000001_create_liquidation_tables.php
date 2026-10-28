<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash Advance Liquidation + Questionable Expense (ERD "Cash Advance
     * Liquidation" block). One liquidation per cash advance. Items carry the
     * Philippine VAT split; a Questionable Expense hangs off one item and has
     * its own workflow.
     */
    public function up(): void
    {
        // Master data (Admin maintains these).
        foreach (['nature_of_costs', 'document_types'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('code', 30)->unique();
                $table->string('label', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Status lookups.
        foreach (['liquidation_statuses', 'liquidation_item_statuses', 'questionable_expense_statuses'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('label', 100);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('cash_advance_liquidations', function (Blueprint $table) {
            $table->id();
            $table->string('control_no', 30)->unique();
            $table->foreignId('fund_request_id')->unique()->constrained('fund_requests')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->date('date_prepared');
            $table->text('purpose')->nullable();

            // Snapshot of the cash advance that was disbursed.
            $table->decimal('cash_advance_amount', 15, 2);
            // All derived from the items by CashAdvanceLiquidation::recalculateTotals().
            $table->decimal('total_amount', 15, 2)->default(0);        // every item, gross
            $table->decimal('liquidated_amount', 15, 2)->default(0);   // items tagged Expense
            $table->decimal('amount_to_return', 15, 2)->default(0);    // unspent cash the requestor gives back
            $table->decimal('amount_to_reimburse', 15, 2)->default(0); // company owes the requestor

            $table->foreignId('liquidation_status_id')->constrained('liquidation_statuses')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('cash_return_reference', 100)->nullable(); // OR/ref of cash returned, at closure
            $table->timestamps();
        });

        Schema::create('liquidation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidation_id')->constrained('cash_advance_liquidations')->cascadeOnDelete();
            $table->date('item_date');
            $table->string('purpose', 255);
            $table->string('reference', 100)->nullable();   // OR / invoice no.
            $table->string('supplier_name', 150);
            $table->string('tin', 20)->nullable();
            $table->foreignId('nature_of_cost_id')->constrained('nature_of_costs')->restrictOnDelete();

            $table->string('vat_nature', 10);                       // VAT | NON_VAT
            $table->decimal('vat_rate', 5, 2)->default(0);          // rate applied (12.00 for VAT)
            $table->decimal('gross_amount', 15, 2);                 // VAT-inclusive, as on the receipt
            $table->decimal('net_amount', 15, 2);
            $table->decimal('input_tax', 15, 2)->default(0);

            $table->foreignId('liquidation_item_status_id')->constrained('liquidation_item_statuses')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('liquidation_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidation_item_id')->constrained('liquidation_items')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->string('file_name', 255);          // original name, label only
            $table->string('file_path', 500);          // private disk
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        // Append-only workflow history (ERD "LiquidationApproval", extended with role + from/to status).
        Schema::create('liquidation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidation_id')->constrained('cash_advance_liquidations')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->unsignedTinyInteger('approval_level')->nullable();
            $table->foreignId('approval_status_id')->constrained('approval_statuses')->restrictOnDelete();
            $table->foreignId('from_liquidation_status_id')->nullable()->constrained('liquidation_statuses')->restrictOnDelete();
            $table->foreignId('to_liquidation_status_id')->constrained('liquidation_statuses')->restrictOnDelete();
            $table->timestamp('approval_date');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        // APV (Accounting Staff) + CV / reimbursement (Treasury) for the amount owed to the requestor.
        Schema::create('liquidation_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidation_id')->unique()->constrained('cash_advance_liquidations')->restrictOnDelete();
            $table->string('apv_no', 30)->unique();
            $table->date('apv_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->string('cv_no', 30)->nullable()->unique();
            $table->date('cv_date')->nullable();
            $table->string('payment_mode', 30)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('reimbursed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('questionable_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('control_no', 30)->unique();
            $table->foreignId('liquidation_item_id')->unique()->constrained('liquidation_items')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('questionable_expense_statuses')->restrictOnDelete();

            $table->foreignId('tagged_by')->constrained('users')->restrictOnDelete();
            $table->string('tagged_role', 20);                 // OE | ACCT_STAFF
            $table->timestamp('tagged_at');
            $table->text('reason');

            // Step 1 — hard copy check (Accounting Staff)
            $table->date('hard_copy_received_on')->nullable();
            $table->boolean('hard_copy_valid')->nullable();
            $table->foreignId('hard_copy_checked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('hard_copy_checked_at')->nullable();
            $table->text('hard_copy_remarks')->nullable();

            // Steps 3-4 — Incident Report (Staff generates, Approver/OE receives)
            $table->string('ir_no', 50)->nullable();
            $table->date('ir_date')->nullable();
            $table->foreignId('ir_generated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('ir_received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('ir_received_at')->nullable();

            // Steps 5-6 — salary deduction (Crewing); proof is stored as attachments
            $table->decimal('deduction_amount', 15, 2)->nullable();
            $table->text('deduction_remarks')->nullable();
            $table->foreignId('deduction_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('deduction_at')->nullable();
            $table->foreignId('proof_secured_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('proof_secured_at')->nullable();

            // Step 7 — closure
            $table->string('resolution', 20)->nullable();      // EXPENSE | DEDUCTED
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('questionable_expense_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionable_expense_id')->constrained('questionable_expenses')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('action', 60);
            $table->foreignId('from_status_id')->nullable()->constrained('questionable_expense_statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->constrained('questionable_expense_statuses')->restrictOnDelete();
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'questionable_expense_events', 'questionable_expenses', 'liquidation_vouchers',
            'liquidation_approvals', 'liquidation_item_attachments', 'liquidation_items',
            'cash_advance_liquidations', 'questionable_expense_statuses',
            'liquidation_item_statuses', 'liquidation_statuses', 'document_types', 'nature_of_costs',
        ] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
