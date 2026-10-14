<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete(); // annonceur au moment de la demande
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('new');
            $table->string('preferred_channel', 10);
            $table->string('requester_phone'); // donnée personnelle : visible du demandeur et de l'annonceur seulement
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamp('unanswered_reminded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->index(['requester_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT inquiries_status_check CHECK (status IN ('new','in_progress','visit_scheduled','concluded','lost','closed'))");
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT inquiries_channel_check CHECK (preferred_channel IN ('phone','whatsapp','message'))");
        // Une seule demande ouverte par demandeur et par annonce : une nouvelle demande reprend le fil existant.
        DB::statement("CREATE UNIQUE INDEX inquiries_one_open ON inquiries (listing_id, requester_id) WHERE status NOT IN ('concluded','lost','closed')");

        Schema::create('inquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable(); // lu par l'autre partie
            $table->timestamps();
            $table->index(['inquiry_id', 'id']);
        });

        // Notes internes : jamais visibles du demandeur.
        Schema::create('inquiry_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index('inquiry_id');
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->restrictOnDelete(); // auteur de la dernière proposition
            $table->jsonb('proposed_slots'); // 1 à 3 créneaux ISO 8601 (UTC)
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 12)->default('proposed');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('reminder_2h_sent_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->boolean('feedback_available')->nullable(); // retour du demandeur après la visite
            $table->boolean('feedback_conform')->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
            $table->index(['listing_id', 'status', 'scheduled_at']);
        });
        DB::statement("ALTER TABLE visits ADD CONSTRAINT visits_status_check CHECK (status IN ('proposed','confirmed','rescheduled','cancelled','done'))");
        // Une seule visite active (non terminée) par demande.
        DB::statement("CREATE UNIQUE INDEX visits_one_active ON visits (inquiry_id) WHERE status IN ('proposed','confirmed','rescheduled')");

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_xof_snapshot')->nullable(); // pour détecter une baisse de prix
            $table->timestamp('unavailable_notified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'listing_id']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->jsonb('criteria');
            $table->string('frequency', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at'); // les annonces antérieures à la création ne déclenchent aucune alerte
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'frequency']);
            $table->index('user_id');
        });
        DB::statement("ALTER TABLE saved_searches ADD CONSTRAINT saved_searches_frequency_check CHECK (frequency IN ('immediate','daily'))");

        // Une annonce n'alerte qu'une fois par recherche enregistrée.
        Schema::create('saved_search_matches', function (Blueprint $table) {
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('matched_at');
            $table->timestamp('notified_at')->nullable();
            $table->primary(['saved_search_id', 'listing_id']);
        });

        // Notifications dans l'application (les canaux push, e-mail, WhatsApp et SMS arrivent en Phase 7).
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'read_at', 'id']);
            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('saved_search_matches');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('inquiry_notes');
        Schema::dropIfExists('inquiry_messages');
        Schema::dropIfExists('inquiries');
    }
};
