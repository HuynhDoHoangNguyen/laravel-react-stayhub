<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->char('payload_fingerprint', 64);
            $table->enum('status', ['PROCESSING', 'FAILED', 'SUCCEEDED']);
            $table->unsignedTinyInteger('attempts');
            $table->uuid('processing_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->foreignId('chat_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->foreignId('assistant_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at');
            $table->unique(['user_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_turns');
    }
};
