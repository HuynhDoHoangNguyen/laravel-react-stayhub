<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_reports', function (Blueprint $table): void {
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->index(['status', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('host_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['status', 'created_at', 'id']);
            $table->dropColumn(['reviewed_at', 'review_note']);
        });
    }
};
