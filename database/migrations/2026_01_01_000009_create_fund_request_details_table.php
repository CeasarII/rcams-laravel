<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('fund_requests')->cascadeOnDelete();
            $table->string('purpose', 255);
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_request_details');
    }
};
