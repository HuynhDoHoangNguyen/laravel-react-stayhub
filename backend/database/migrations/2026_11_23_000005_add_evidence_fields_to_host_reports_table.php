<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_reports', function (Blueprint $table): void {
            $table->string('evidence_path')->nullable();
            $table->string('evidence_mime', 32)->nullable();
            $table->unsignedInteger('evidence_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('host_reports', function (Blueprint $table): void {
            $table->dropColumn(['evidence_path', 'evidence_mime', 'evidence_size']);
        });
    }
};
