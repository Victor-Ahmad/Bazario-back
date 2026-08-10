<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->string('refund_status')->nullable()->after('paid_at');
            $table->json('metadata')->nullable()->after('refund_status');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'refund_status', 'metadata']);
        });
    }
};
