<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('cancel_late_policy', 24)
                ->default('deny')
                ->after('cancel_cutoff_hours');
            $table->string('edit_late_policy', 24)
                ->default('deny')
                ->after('edit_cutoff_hours');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['cancel_late_policy', 'edit_late_policy']);
        });
    }
};
