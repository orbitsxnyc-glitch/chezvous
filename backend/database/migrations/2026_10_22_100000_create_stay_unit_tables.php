<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // L'unité est la capacité réservable d'un bien : une villa entière = 1 unité, une résidence = n unités.
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('name', 120);
            $table->unsignedSmallInteger('capacity_guests');
            $table->unsignedSmallInteger('beds')->nullable();
            $table->string('status', 12)->default('draft');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['property_id', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->foreign('agency_id')->references('id')->on('agencies')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE units ADD CONSTRAINT units_status_check CHECK (status IN ('draft','active','paused','archived'))");
        DB::statement('ALTER TABLE units ADD CONSTRAINT units_capacity_check CHECK (capacity_guests > 0)');

        // Tarif de base d'une unité (montants entiers en XOF). Un seul plan par unité au MVP.
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('XOF');
            $table->unsignedBigInteger('base_nightly_xof');
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->unsignedSmallInteger('max_nights')->nullable();
            $table->unsignedBigInteger('cleaning_fee_xof')->default(0);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE rate_plans ADD CONSTRAINT rate_plans_currency_check CHECK (currency = 'XOF')");
        DB::statement('ALTER TABLE rate_plans ADD CONSTRAINT rate_plans_nights_check CHECK (min_nights >= 1 AND (max_nights IS NULL OR max_nights >= min_nights))');

        // Exceptions de prix (saisons, week-ends). Intervalle demi-ouvert [starts_on, ends_on) : la nuit du départ n'est pas incluse.
        Schema::create('rate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedBigInteger('nightly_xof');
            $table->unsignedSmallInteger('min_nights')->nullable();
            $table->smallInteger('priority')->default(0); // en cas de chevauchement, la plus haute priorité l'emporte
            $table->timestamps();
            $table->index(['unit_id', 'starts_on', 'ends_on']);
        });
        DB::statement('ALTER TABLE rate_overrides ADD CONSTRAINT rate_overrides_range_check CHECK (ends_on > starts_on)');

        // Fermetures du calendrier posées par l'hôte (ou importées). Les séjours confirmés auront leur propre table (lot 3).
        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('kind', 12)->default('manual');
            $table->string('source', 60)->nullable(); // fournisseur d'un import (iCal, plus tard)
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'starts_on', 'ends_on']);
        });
        DB::statement('ALTER TABLE calendar_blocks ADD CONSTRAINT calendar_blocks_range_check CHECK (ends_on > starts_on)');
        DB::statement("ALTER TABLE calendar_blocks ADD CONSTRAINT calendar_blocks_kind_check CHECK (kind IN ('manual','maintenance','external'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_blocks');
        Schema::dropIfExists('rate_overrides');
        Schema::dropIfExists('rate_plans');
        Schema::dropIfExists('units');
    }
};
