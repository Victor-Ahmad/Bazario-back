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
        Schema::create('service_provider_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week'); // 0=Sun ... 6=Sat
            $table->time('start_time'); // local time in provider timezone
            $table->time('end_time');   // local time in provider timezone

            $table->timestamps();

            $table->index(['service_provider_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_provider_working_hours');
    }
};
