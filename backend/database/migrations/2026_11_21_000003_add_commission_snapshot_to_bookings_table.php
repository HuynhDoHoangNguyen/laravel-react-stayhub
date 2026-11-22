<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('commission_rate_bps')->nullable()->after('room_total');
            $table->foreignId('host_agreement_acceptance_id')->nullable()->after('commission_rate_bps')
                ->constrained('host_agreement_acceptances')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('host_agreement_acceptance_id');
            $table->dropColumn('commission_rate_bps');
        });
    }
};
