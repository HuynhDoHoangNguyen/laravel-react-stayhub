<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32)->unique();
            $table->string('title', 255);
            $table->longText('terms_body');
            $table->char('content_sha256', 64);
            $table->unsignedSmallInteger('commission_rate_bps')->default(1000); // 1000 bps = 10.00%
            $table->dateTime('effective_at');
            $table->dateTime('retired_at')->nullable();
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'RETIRED'])->default('DRAFT');
            $table->timestamps();

            $table->index(['status', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_agreements');
    }
};
