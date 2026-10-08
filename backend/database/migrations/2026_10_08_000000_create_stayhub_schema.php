<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. users: add missing ERD columns wrapped in Schema::hasColumn
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address', 500)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar', 500)->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['ADMIN', 'HOST', 'CUSTOMER'])->default('CUSTOMER')->after('avatar');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['ACTIVE', 'INACTIVE', 'BLOCKED'])->default('ACTIVE')->after('role');
            }
        });

        // 2. properties
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

        // 3. property_images
        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->string('image_path', 500);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        // 4. room_types
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 5. rooms
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignId('room_type_id')->constrained('room_types')->onDelete('cascade');
            $table->string('room_number', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('capacity');
            $table->decimal('price_per_night', 12, 2);
            $table->enum('status', ['ACTIVE', 'MAINTENANCE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        // 6. room_images
        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->string('image_path', 500);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        // 7. amenities
        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // 8. room_amenities
        Schema::create('room_amenities', function (Blueprint $table) {
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->foreignId('amenity_id')->constrained('amenities')->onDelete('cascade');
            $table->primary(['room_id', 'amenity_id']);
        });

        // 9. maintenances
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->enum('status', ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('SCHEDULED');
            $table->timestamps();
        });

        // 10. services
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('unit', 50);
            $table->decimal('price', 12, 2);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['property_id', 'status']);
        });

        // 11. bookings
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

        // 12. booking_services
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('service_name', 255);
            $table->string('unit', 50);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('booking_id');
        });

        // 13. invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code', 50)->unique();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->restrictOnDelete();
            $table->string('customer_name', 255);
            $table->string('customer_email', 255);
            $table->string('customer_phone', 20)->nullable();
            $table->string('property_name', 255);
            $table->string('room_name', 255);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('extra_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->enum('payment_status', ['UNPAID', 'PAID', 'REFUNDED'])->default('UNPAID');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 14. invoice_items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->enum('item_type', ['ROOM', 'SERVICE', 'SURCHARGE', 'DISCOUNT', 'OTHER']);
            $table->string('item_name', 255);
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        // 15. reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        // 16. faqs
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 500);
            $table->text('answer');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // 17. chat_sessions
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255)->nullable();
            $table->timestamps();
        });

        // 18. chat_messages
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->enum('sender', ['USER', 'ASSISTANT']);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('booking_services');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('services');
        Schema::dropIfExists('maintenances');
        Schema::dropIfExists('room_amenities');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('room_images');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('properties');

        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'status')) {
                $columnsToDrop[] = 'status';
            }
            if (Schema::hasColumn('users', 'role')) {
                $columnsToDrop[] = 'role';
            }
            if (Schema::hasColumn('users', 'avatar')) {
                $columnsToDrop[] = 'avatar';
            }
            if (Schema::hasColumn('users', 'address')) {
                $columnsToDrop[] = 'address';
            }
            if (Schema::hasColumn('users', 'phone')) {
                $columnsToDrop[] = 'phone';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
