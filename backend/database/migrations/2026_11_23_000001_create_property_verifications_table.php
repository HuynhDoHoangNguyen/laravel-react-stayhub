<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('address_snapshot', 500);
            $table->string('status', 24);
            $table->string('document_path')->unique();
            $table->string('document_mime', 64);
            $table->unsignedInteger('document_size');
            $table->timestamp('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_verifications');
    }
};
