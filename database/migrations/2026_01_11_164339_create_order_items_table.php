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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->morphs('purchasable');

            $table->string('title_snapshot')->nullable();
            $table->text('description_snapshot')->nullable();

            $table->unsignedInteger('quantity')->default(1);

            $table->bigInteger('unit_amount');
            $table->bigInteger('gross_amount');
            $table->bigInteger('platform_fee_amount')->default(0);
            $table->bigInteger('net_amount');

            $table->foreignId('payee_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 32)->default('pending');
            // status: pending, fulfilled, cancelled, refunded

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['payee_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
