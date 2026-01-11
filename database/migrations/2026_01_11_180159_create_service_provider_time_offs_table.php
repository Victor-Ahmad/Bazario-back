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
        Schema::create('service_provider_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();

            $table->timestamp('starts_at'); // UTC
            $table->timestamp('ends_at');   // UTC
            $table->boolean('is_holiday')->default(false);
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['service_provider_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_provider_time_offs');
    }
};
