<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_days')->default(1)->after('price');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_days')->default(1)->after('price');
            $table->timestamp('expires_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['duration_days', 'expires_at']);
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn('duration_days');
        });
    }
};
