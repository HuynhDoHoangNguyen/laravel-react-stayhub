<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_agreement_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_agreement_id')->constrained('partner_agreements')->restrictOnDelete();
            $table->char('accepted_content_sha256', 64);
            $table->dateTime('accepted_at');
            $table->string('method', 32)->default('CHECKBOX_CLICKWRAP');
            $table->timestamps();

            $table->unique(['host_id', 'partner_agreement_id'], 'host_agreement_unique');
            $table->index(['host_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_agreement_acceptances');
    }
};
