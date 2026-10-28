<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_request_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // CASH_ADVANCE, REVOLVING_FUND
            $table->string('label', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // "who holds the record" states — see Phase 1 §5.
        Schema::create('request_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Action taken at a transition — feeds fund_request_approvals.action_status_id.
        Schema::create('approval_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // SUBMITTED, VALIDATED, RETURNED, APPROVED, NOT_APPROVED, APV_CREATED, CV_CREATED, DISBURSED, CANCELLED
            $table->string('label', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_statuses');
        Schema::dropIfExists('request_statuses');
        Schema::dropIfExists('fund_request_types');
    }
};
