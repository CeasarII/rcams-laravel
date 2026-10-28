<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('message');
            $table->string('reference_type', 100); // e.g. FundRequest
            $table->unsignedBigInteger('reference_id');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 60);       // e.g. FundRequest
            $table->unsignedBigInteger('record_id');
            $table->string('action', 60);       // e.g. Created, Updated, Submitted
            $table->text('old_value')->nullable(); // JSON snapshot
            $table->text('new_value')->nullable(); // JSON snapshot
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
