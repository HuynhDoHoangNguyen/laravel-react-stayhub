<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 50)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('guest_count');
            $table->unsignedInteger('number_of_nights');
            $table->decimal('price_per_night', 12, 2);
            $table->decimal('room_total', 12, 2);
            $table->string('status', 20)->default('PENDING')->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status', 'check_in_date', 'check_out_date']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
