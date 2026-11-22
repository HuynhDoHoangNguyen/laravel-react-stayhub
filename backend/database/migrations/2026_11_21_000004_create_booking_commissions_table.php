<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('host_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('host_agreement_acceptance_id')->constrained('host_agreement_acceptances')->restrictOnDelete();
            $table->string('reference_code', 64)->unique();
            $table->decimal('commission_base', 12, 2);
            $table->unsignedSmallInteger('commission_rate_bps');
            $table->decimal('commission_amount', 12, 2);
            $table->char('currency', 3)->default('VND');
            $table->string('status', 32)->default('AWAITING_CUSTOMER_PAYMENT');
            $table->string('void_reason', 64)->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();

            $table->index(['host_id', 'status', 'due_at'], 'commissions_host_status_due_idx');
            $table->index(['status', 'settled_at'], 'commissions_status_settled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_commissions');
    }
};
