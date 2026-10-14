<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('localities')->restrictOnDelete();
            $table->string('level', 20);
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 3)->nullable(); // région : « DK » ; quartier : « YOF » (référence du bien)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'level']);
        });
        DB::statement("ALTER TABLE localities ADD CONSTRAINT localities_level_check CHECK (level IN ('region', 'department', 'commune', 'neighborhood'))");
        // Point de repère (approximatif) et contour éventuel, en WGS84.
        DB::statement('ALTER TABLE localities ADD COLUMN centroid geography(Point, 4326)');
        DB::statement('ALTER TABLE localities ADD COLUMN geom geometry(MultiPolygon, 4326)');
        DB::statement('CREATE INDEX localities_centroid_gist ON localities USING GIST (centroid)');
        DB::statement('CREATE INDEX localities_geom_gist ON localities USING GIST (geom)');

        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('moderation_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->string('kind', 20); // rejection | report
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE moderation_reasons ADD CONSTRAINT moderation_reasons_kind_check CHECK (kind IN ('rejection', 'report'))");

        // Références lisibles : DK-YOF-000123 pour le bien, CV-2026-000123 pour l'annonce.
        DB::statement('CREATE SEQUENCE IF NOT EXISTS property_reference_seq');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS listing_reference_seq');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS listing_reference_seq');
        DB::statement('DROP SEQUENCE IF EXISTS property_reference_seq');
        Schema::dropIfExists('moderation_reasons');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('property_types');
        Schema::dropIfExists('localities');
    }
};
