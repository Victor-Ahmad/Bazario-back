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
        Schema::create('stripe_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payee_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('transfer_id')->unique();
            $table->bigInteger('amount');
            $table->string('currency_iso', 3);

            $table->string('status', 32)->default('created'); // created, failed, reversed

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'payee_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_transfers');
    }
};
