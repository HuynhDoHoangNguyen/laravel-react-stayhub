<?php
// TEMP-M1-STUB

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('password');
            $table->string('address', 500)->nullable()->after('phone');
            $table->string('avatar', 500)->nullable()->after('address');
            $table->enum('role', ['ADMIN', 'HOST', 'CUSTOMER'])->default('CUSTOMER')->after('avatar');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'BLOCKED'])->default('ACTIVE')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'avatar', 'role', 'status']);
        });
    }
};
