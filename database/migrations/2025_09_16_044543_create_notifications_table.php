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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('notification_type', ['workout_reminder', 'achievement', 'group_invite', 'system', 'social', 'email_verification']);
            $table->string('title');
            $table->text('message');
            $table->json('action_data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->boolean('email_sent')->default(false);
            $table->timestamp('scheduled_time')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['notification_type', 'email_sent', 'created_at']);
            $table->index(['user_id', 'notification_type', 'is_sent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
