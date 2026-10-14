<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le bien physique : il existe une seule fois, même avec plusieurs annonces (Passeport du Bien).
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('reference', 20)->nullable()->unique(); // attribuée dès que la localité est connue
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('property_type_id')->constrained('property_types')->restrictOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('localities')->restrictOnDelete();
            $table->string('address')->nullable();
            $table->unsignedInteger('surface_m2')->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->boolean('furnished')->default(false);
            $table->jsonb('attributes')->nullable(); // champs propres au type (étage, statut foncier…)
            $table->string('location_precision', 12)->default('approximate');
            $table->timestamps();
            $table->softDeletes();
            $table->index('locality_id');
            $table->index('property_type_id');
        });
        DB::statement("ALTER TABLE properties ADD CONSTRAINT properties_location_precision_check CHECK (location_precision IN ('exact', 'approximate', 'hidden'))");
        DB::statement('ALTER TABLE properties ADD COLUMN location geography(Point, 4326)');
        DB::statement('CREATE INDEX properties_location_gist ON properties USING GIST (location)');
        DB::statement('CREATE INDEX properties_attributes_gin ON properties USING GIN (attributes)');

        Schema::create('property_amenity', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->restrictOnDelete();
            $table->primary(['property_id', 'amenity_id']);
        });

        Schema::create('property_images', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // fichier original retraité, disque public
            $table->jsonb('sizes')->nullable(); // { thumb, medium, large } -> chemins
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->string('status', 12)->default('processing');
            $table->timestamps();
            $table->index(['property_id', 'position']);
        });
        DB::statement("ALTER TABLE property_images ADD CONSTRAINT property_images_status_check CHECK (status IN ('processing', 'ready', 'failed'))");
        DB::statement('CREATE UNIQUE INDEX property_images_one_cover ON property_images (property_id) WHERE is_cover');

        // L'annonce : un annonceur publie un bien sous certaines conditions commerciales.
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('reference', 20)->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('advertiser_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('agency_id')->nullable(); // clé étrangère ajoutée en Phase 4
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('mandate_id')->nullable();
            $table->string('transaction', 24);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_xof')->nullable();
            $table->boolean('negotiable')->default(false);
            $table->unsignedSmallInteger('deposit_months')->nullable();
            $table->unsignedSmallInteger('advance_months')->nullable();
            $table->unsignedBigInteger('agency_fee_xof')->nullable();
            $table->unsignedBigInteger('other_fees_xof')->nullable();
            $table->string('status', 24)->default('draft');
            $table->unsignedSmallInteger('draft_step')->default(1);
            $table->string('rejection_reason_code', 40)->nullable();
            $table->text('rejection_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expiry_reminded_at')->nullable();
            $table->timestamp('closed_at')->nullable(); // vendue ou louée
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('contacts_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'transaction', 'price_xof']);
            $table->index(['advertiser_user_id', 'status']);
            $table->index('property_id');
            $table->index('expires_at');
        });
        DB::statement("ALTER TABLE listings ADD CONSTRAINT listings_status_check CHECK (status IN ('draft','pending_moderation','published','rejected','paused','closed','expired','hidden','archived'))");
        DB::statement("ALTER TABLE listings ADD CONSTRAINT listings_transaction_check CHECK (transaction IN ('sale','rent_long','rent_furnished_short'))");

        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('reason_code', 40);
            $table->text('comment')->nullable();
            $table->string('status', 12)->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['listing_id', 'status']);
        });
        DB::statement("ALTER TABLE listing_reports ADD CONSTRAINT listing_reports_status_check CHECK (status IN ('open','dismissed','actioned'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_reports');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('property_images');
        Schema::dropIfExists('property_amenity');
        Schema::dropIfExists('properties');
    }
};
