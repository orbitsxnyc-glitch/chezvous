<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('provider', 24); // manual | wave | orange_money (adaptateurs)
            $table->string('provider_ref', 60); // référence que le voyageur indique au règlement
            $table->string('method', 16);
            $table->unsignedBigInteger('amount_xof');
            $table->string('currency', 3)->default('XOF');
            $table->string('status', 20)->default('created');
            $table->string('declared_reference', 120)->nullable(); // référence de transaction saisie par le voyageur : indicative, jamais une preuve
            $table->timestamp('declared_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_ref']);
            $table->index(['booking_id', 'status']);
            $table->index(['status', 'declared_at']);
        });
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_status_check CHECK (status IN ('created','pending','succeeded','failed','expired','cancelled','refunded','partially_refunded'))");
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_currency_check CHECK (currency = 'XOF')");
        // Un seul règlement en cours par séjour.
        DB::statement("CREATE UNIQUE INDEX payment_intents_one_active ON payment_intents (booking_id) WHERE status IN ('created','pending')");
        // Un seul règlement réussi par séjour : pas de double encaissement.
        DB::statement("CREATE UNIQUE INDEX payment_intents_one_succeeded ON payment_intents (booking_id) WHERE status IN ('succeeded','refunded','partially_refunded')");

        // Journal des événements de paiement : (fournisseur, identifiant externe) unique = un événement n'est traité qu'une fois.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider', 24);
            $table->string('external_event_id', 120);
            $table->string('kind', 24); // succeeded | failed | quarantined | late_payment | refund
            $table->string('payload_digest', 64)->nullable();
            $table->jsonb('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'external_event_id']);
            $table->index('payment_intent_id');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('payment_intent_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('reason', 60);
            $table->unsignedBigInteger('amount_xof');
            $table->string('status', 12)->default('requested');
            $table->string('provider_ref', 120)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('requested','succeeded','failed'))");
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount_xof > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_intents');
    }
};
