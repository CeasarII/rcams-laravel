<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only workflow history for fund_requests. One row per transition
     * (Submitted, Validated, Returned, Approved, Not Approved, APV Created,
     * CV Created, Disbursed, Cancelled) — see Phase 1 §4 transition table.
     * Keeps the ERD's original table name; extended with from/to status and role.
     */
    public function up(): void
    {
        Schema::create('fund_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('fund_requests')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();

            // Nullable: only meaningful for the OE/Staff/Head validation steps,
            // not for Submit, APV Created, CV Created, Disbursed, Cancelled.
            $table->unsignedTinyInteger('approval_level')->nullable();

            $table->foreignId('approval_status_id')->constrained('approval_statuses')->restrictOnDelete();
            $table->foreignId('from_request_status_id')->nullable()->constrained('request_statuses')->restrictOnDelete();
            $table->foreignId('to_request_status_id')->constrained('request_statuses')->restrictOnDelete();

            $table->timestamp('approval_date');
            $table->text('comments')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_request_approvals');
    }
};
