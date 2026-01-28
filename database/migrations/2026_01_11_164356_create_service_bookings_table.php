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
        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')
                ->nullable()
                ->unique()
                ->constrained('order_items')
                ->nullOnDelete();

            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();

            $table->foreignId('provider_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 32)->default('requested');
            // status: requested, confirmed, in_progress, completed, cancelled_by_customer, cancelled_by_provider, no_show

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone')->nullable();

            $table->string('location_type', 32)->nullable();
            // remote, on_site, at_customer

            $table->json('location_payload')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['provider_user_id', 'starts_at']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_bookings');
    }
};
