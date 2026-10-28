<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            // Polymorphic: attachable_type / attachable_id. No FK constraint —
            // enforced in the application, since it spans multiple tables
            // (fund_requests today; liquidations, expenses later).
            $table->string('attachable_type', 100);
            $table->unsignedBigInteger('attachable_id');

            $table->string('original_file_name', 255);
            $table->string('stored_file_name', 255); // generated, never trust the client's name
            $table->string('file_path', 500);        // private disk path, served via authorized controller
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');  // bytes

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');

            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
