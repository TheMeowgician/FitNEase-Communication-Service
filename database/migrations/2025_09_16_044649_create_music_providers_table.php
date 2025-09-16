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
        Schema::create('music_providers', function (Blueprint $table) {
            $table->id('provider_id');
            $table->string('provider_name')->unique();
            $table->string('display_name');
            $table->string('oauth_authorize_url');
            $table->string('oauth_token_url');
            $table->string('client_id');
            $table->text('client_secret');
            $table->json('supported_scopes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['provider_name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_providers');
    }
};
