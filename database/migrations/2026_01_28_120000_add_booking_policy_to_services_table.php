<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('cancel_cutoff_hours')
                ->default(24)
                ->after('slot_interval_minutes');
            $table->unsignedInteger('edit_cutoff_hours')
                ->default(24)
                ->after('cancel_cutoff_hours');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['cancel_cutoff_hours', 'edit_cutoff_hours']);
        });
    }
};
