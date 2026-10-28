<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revolving Fund (ERD "Revolving Fund" block): the fund, its voyages, a
     * transaction ledger, and the replenishment ("RevolvingFundExpense" +
     * items) that Process B runs. Also links fund requests to a fund and lets
     * a Questionable Expense hang off a revolving-fund expense item.
     */
    public function up(): void
    {
        foreach (['revolving_fund_statuses', 'revolving_fund_voyage_statuses', 'revolving_fund_expense_statuses'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('label', 100);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('revolving_fund_transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 100);
            $table->smallInteger('sign');                       // +1 adds to the balance, -1 deducts
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('revolving_funds', function (Blueprint $table) {
            $table->id();
            $table->string('fund_code', 30)->unique();
            $table->string('fund_name', 150);
            $table->foreignId('vessel_id')->nullable()->constrained('vessels')->restrictOnDelete(); // null = Shore Base fund
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('custodian_id')->constrained('users')->restrictOnDelete();
            $table->decimal('fund_limit', 15, 2);
            $table->decimal('current_balance', 15, 2)->default(0); // only RevolvingFundLedger changes this
            $table->foreignId('status_id')->constrained('revolving_fund_statuses')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('revolving_fund_voyages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revolving_fund_id')->constrained('revolving_funds')->restrictOnDelete();
            $table->string('voyage_no', 30);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('beginning_balance', 15, 2);
            $table->decimal('ending_balance', 15, 2)->nullable();
            $table->foreignId('status_id')->constrained('revolving_fund_voyage_statuses')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['revolving_fund_id', 'voyage_no']);
        });

        // One replenishment request = voyage expense details (Process B).
        Schema::create('revolving_fund_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('control_no', 30)->unique();           // RFE-2026-00001
            $table->foreignId('voyage_id')->constrained('revolving_fund_voyages')->restrictOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->date('expense_date');
            $table->decimal('total_amount', 15, 2)->default(0);   // every item, gross — questionable ones included
            $table->foreignId('status_id')->constrained('revolving_fund_expense_statuses')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('revolving_fund_expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('revolving_fund_expenses')->cascadeOnDelete();
            $table->date('item_date');
            $table->string('purpose', 255);
            $table->text('description')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('supplier_name', 150);
            $table->string('tin', 20)->nullable();
            $table->foreignId('nature_of_cost_id')->constrained('nature_of_costs')->restrictOnDelete();
            $table->string('vat_nature', 10);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->decimal('input_tax', 15, 2)->default(0);
            // Shares the liquidation item statuses: Expense / Questionable / Settled by deduction.
            $table->foreignId('liquidation_item_status_id')
                ->constrained('liquidation_item_statuses', 'id', 'rfe_items_item_status_fk')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('revolving_fund_expense_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('revolving_fund_expenses')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('approval_status_id')->constrained('approval_statuses', 'id', 'rfe_appr_status_fk')->restrictOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('revolving_fund_expense_statuses', 'id', 'rfe_appr_from_fk')->restrictOnDelete();
            $table->foreignId('to_status_id')->constrained('revolving_fund_expense_statuses', 'id', 'rfe_appr_to_fk')->restrictOnDelete();
            $table->timestamp('approval_date');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        // APV (Accounting Staff) + CV and disbursement (Treasury) for the replenishment.
        Schema::create('revolving_fund_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->unique()->constrained('revolving_fund_expenses')->restrictOnDelete();
            $table->string('apv_no', 30)->unique();
            $table->date('apv_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->string('cv_no', 30)->nullable()->unique();
            $table->date('cv_date')->nullable();
            $table->string('payment_mode', 30)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('revolving_fund_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revolving_fund_id')->constrained('revolving_funds')->restrictOnDelete();
            $table->foreignId('fund_request_id')->nullable()->constrained('fund_requests')->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('revolving_fund_expenses')->restrictOnDelete();
            $table->foreignId('transaction_type_id')->constrained('revolving_fund_transaction_types')->restrictOnDelete();
            $table->decimal('amount', 15, 2);                      // always positive; the type gives the direction
            $table->decimal('balance_after', 15, 2);
            $table->date('transaction_date');
            $table->string('reference_no', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Process A link: a Revolving Fund request names the fund it funds.
        Schema::table('fund_requests', function (Blueprint $table) {
            $table->foreignId('revolving_fund_id')->nullable()->after('vessel_id')
                ->constrained('revolving_funds')->restrictOnDelete();
        });

        // A Questionable Expense belongs to either a liquidation item or a revolving-fund expense item.
        Schema::table('questionable_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('liquidation_item_id')->nullable()->change();
            $table->foreignId('revolving_fund_expense_item_id')->nullable()->after('liquidation_item_id')
                ->unique('qe_rfe_item_unique')
                ->constrained('revolving_fund_expense_items', 'id', 'qe_rfe_item_fk')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questionable_expenses', function (Blueprint $table) {
            $table->dropForeign('qe_rfe_item_fk');
            $table->dropUnique('qe_rfe_item_unique');
            $table->dropColumn('revolving_fund_expense_item_id');
        });

        Schema::table('fund_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revolving_fund_id');
        });

        foreach ([
            'revolving_fund_transactions', 'revolving_fund_vouchers', 'revolving_fund_expense_approvals',
            'revolving_fund_expense_items', 'revolving_fund_expenses', 'revolving_fund_voyages',
            'revolving_funds', 'revolving_fund_transaction_types', 'revolving_fund_expense_statuses',
            'revolving_fund_voyage_statuses', 'revolving_fund_statuses',
        ] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
