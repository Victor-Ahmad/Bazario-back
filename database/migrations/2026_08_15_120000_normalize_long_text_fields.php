<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->text('subtitle')->nullable()->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->json('description_json')->nullable()->after('description');
        });

        DB::table('services')
            ->select(['id', 'description'])
            ->orderBy('id')
            ->chunkById(100, function ($services) {
                foreach ($services as $service) {
                    $description = $service->description;

                    if ($description === null || $description === '') {
                        DB::table('services')
                            ->where('id', $service->id)
                            ->update(['description_json' => null]);

                        continue;
                    }

                    $decoded = json_decode($description, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $normalized = $decoded;
                    } else {
                        $normalized = ['en' => $description];
                    }

                    DB::table('services')
                        ->where('id', $service->id)
                        ->update([
                            'description_json' => json_encode($normalized, JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->renameColumn('description_json', 'description');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->text('description_text')->nullable()->after('description');
        });

        DB::table('services')
            ->select(['id', 'description'])
            ->orderBy('id')
            ->chunkById(100, function ($services) {
                foreach ($services as $service) {
                    $description = $service->description;

                    if ($description === null || $description === '') {
                        DB::table('services')
                            ->where('id', $service->id)
                            ->update(['description_text' => null]);

                        continue;
                    }

                    $decoded = json_decode($description, true);

                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                        $fallback = (string) $description;
                    } else {
                        $fallback = (string) ($decoded['en'] ?? reset($decoded) ?: '');
                    }

                    DB::table('services')
                        ->where('id', $service->id)
                        ->update(['description_text' => $fallback]);
                }
            });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->renameColumn('description_text', 'description');
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->change();
        });
    }
};
