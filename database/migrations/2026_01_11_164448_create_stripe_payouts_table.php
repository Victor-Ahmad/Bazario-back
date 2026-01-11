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
        Schema::create('stripe_payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stripe_account_id')->index();

            $table->string('payout_id')->unique();
            $table->bigInteger('amount');
            $table->string('currency_iso', 3);

            $table->string('status', 32)->nullable(); // pending, paid, failed, canceled
            $table->json('metadata')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_payouts');
    }
};
