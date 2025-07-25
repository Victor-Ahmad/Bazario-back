<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ad_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        // Optionally, seed some positions
        DB::table('ad_positions')->insert([
            ['name' => 'landing_top', 'label' => 'Landing Page - Top', 'priority' => 1],
            ['name' => 'below_main', 'label' => 'Below Main Section', 'priority' => 2],
            ['name' => 'footer', 'label' => 'Footer Section', 'priority' => 3],
        ]);
    }
    public function down(): void
    {
        Schema::dropIfExists('ad_positions');
    }
};
