<?php
// TEMP-M1-STUB

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['HOTEL', 'HOMESTAY']);
            $table->string('address', 500);
            $table->text('description')->nullable();
            $table->string('phone', 20)->nullable();
            $table->time('check_in_time')->default('14:00:00');
            $table->time('check_out_time')->default('12:00:00');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING'])->default('ACTIVE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
