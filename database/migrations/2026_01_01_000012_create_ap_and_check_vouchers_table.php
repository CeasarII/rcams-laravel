<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->unique()->constrained('fund_requests')->restrictOnDelete();
            $table->string('apv_no', 30)->unique();
            $table->date('apv_date');
            $table->decimal('amount', 15, 2); // snapshot of fund_requests.amount_requested at APV creation
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Created at step 6 (CV No. provided) and completed at step 7 (Disburse) —
        // disbursed_at/amount_disbursed stay null in between.
        Schema::create('check_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_voucher_id')->unique()->constrained('ap_vouchers')->restrictOnDelete();
            $table->string('cv_no', 30)->unique();
            $table->date('cv_date');
            $table->decimal('amount_disbursed', 15, 2)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('payment_mode', 30)->nullable(); // e.g. Check, Bank Transfer
            $table->string('payment_reference', 100)->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_vouchers');
        Schema::dropIfExists('ap_vouchers');
    }
};
