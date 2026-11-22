<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_commission_id')->constrained('booking_commissions')->restrictOnDelete();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('bank_transaction_reference', 128)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('status', 32)->default('SUBMITTED');
            $table->dateTime('submitted_at');
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->string('verified_bank_reference', 128)->nullable()->unique();
            $table->timestamps();

            $table->index(['booking_commission_id', 'status'], 'remittances_commission_status_idx');
            $table->index(['status', 'submitted_at'], 'remittances_status_submitted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_remittances');
    }
};
