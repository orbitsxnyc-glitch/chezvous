<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATES = "'requested','accepted','awaiting_payment','confirmed','checked_in','completed','declined','expired','payment_failed','cancelled_by_guest','cancelled_by_host','disputed'";

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::table('units', function (Blueprint $table) {
            $table->string('cancellation_policy', 12)->default('moderate')->after('beds');
        });
        DB::statement("ALTER TABLE units ADD CONSTRAINT units_cancellation_policy_check CHECK (cancellation_policy IN ('flexible','moderate','strict'))");

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('quote_id')->unique()->constrained()->restrictOnDelete(); // un devis ne sert qu'une fois
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 80);
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('guests');
            $table->string('state', 24)->default('requested');
            $table->unsignedBigInteger('total_xof');
            $table->string('currency', 3)->default('XOF');
            $table->jsonb('policy_snapshot'); // politique d'annulation figée à la demande
            $table->timestamp('respond_by')->nullable(); // échéance de réponse de l'hôte
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('payment_due_at')->nullable(); // fin du verrou temporaire
            $table->timestamp('confirmed_at')->nullable();
            $table->string('state_reason', 300)->nullable();
            $table->timestamps();
            $table->unique(['guest_user_id', 'idempotency_key']);
            $table->index(['unit_id', 'state']);
            $table->index(['guest_user_id', 'state']);
            $table->index(['state', 'respond_by']);
            $table->index(['state', 'payment_due_at']);
        });
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_state_check CHECK (state IN ('.self::STATES.'))');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_dates_check CHECK (check_out > check_in)');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_currency_check CHECK (currency = 'XOF')");

        // Occupation des nuits : la base elle-même interdit deux occupations actives qui se chevauchent sur une unité.
        Schema::create('unit_occupancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->string('status', 12); // held (verrou temporaire) | confirmed | checked_in | released
            $table->timestamp('expires_at')->nullable(); // fin du verrou (held uniquement)
            $table->timestamps();
            $table->index(['unit_id', 'status', 'expires_at']);
        });
        DB::statement("ALTER TABLE unit_occupancies ADD CONSTRAINT unit_occupancies_status_check CHECK (status IN ('held','confirmed','checked_in','released'))");
        DB::statement('ALTER TABLE unit_occupancies ADD CONSTRAINT unit_occupancies_dates_check CHECK (check_out > check_in)');
        DB::statement("ALTER TABLE unit_occupancies ADD CONSTRAINT unit_occupancies_no_overlap EXCLUDE USING gist (unit_id WITH =, daterange(check_in, check_out, '[)') WITH &&) WHERE (status IN ('held','confirmed','checked_in'))");

        // Historique des transitions : acteur, raison, horodatage. Jamais supprimé.
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // null : système (expiration)
            $table->string('reason', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['booking_id', 'id']);
        });

        // Événements métier à diffuser (notifications) : écrits dans la même transaction que l'état, traités ensuite avec reprises.
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60);
            $table->string('aggregate_type', 40);
            $table->unsignedBigInteger('aggregate_id');
            $table->jsonb('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 200)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['processed_at', 'failed_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('booking_events');
        Schema::dropIfExists('unit_occupancies');
        Schema::dropIfExists('bookings');
        DB::statement('ALTER TABLE units DROP CONSTRAINT IF EXISTS units_cancellation_policy_check');
        Schema::table('units', fn (Blueprint $t) => $t->dropColumn('cancellation_policy'));
    }
};
