<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recherche insensible aux accents (« Sacre-Coeur » trouve « Sacré-Cœur »).
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        Schema::table('listings', function (Blueprint $table) {
            // Fraîcheur dénormalisée : dernière confirmation de disponibilité et rang (0 frais, 1 à reconfirmer, 2 ancien).
            $table->timestamp('last_confirmed_at')->nullable();
            $table->string('availability_response', 12)->default('available');
            $table->unsignedSmallInteger('freshness_bucket')->default(2);
        });
        DB::statement("ALTER TABLE listings ADD CONSTRAINT listings_availability_response_check CHECK (availability_response IN ('available','reserved','rented','withdrawn'))");
        DB::statement("CREATE INDEX listings_public_rank_idx ON listings (freshness_bucket, published_at DESC, id DESC) WHERE status = 'published'");
        DB::statement("CREATE INDEX listings_public_price_idx ON listings (transaction, price_xof, id) WHERE status = 'published'");

        // Position publique : exacte, décalée (approximative) ou absente. La recherche par zone n'utilise QUE celle-ci,
        // jamais la position exacte, pour qu'un balayage par emprise ne permette pas de la retrouver.
        DB::statement('ALTER TABLE properties ADD COLUMN public_location geography(Point, 4326)');
        DB::statement('CREATE INDEX properties_public_location_gist ON properties USING GIST (public_location)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS properties_public_location_gist');
        DB::statement('ALTER TABLE properties DROP COLUMN IF EXISTS public_location');
        DB::statement('DROP INDEX IF EXISTS listings_public_price_idx');
        DB::statement('DROP INDEX IF EXISTS listings_public_rank_idx');
        Schema::table('listings', fn (Blueprint $t) => $t->dropColumn(['last_confirmed_at', 'availability_response', 'freshness_bucket']));
    }
};
