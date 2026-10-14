<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('locality_id')->nullable()->constrained('localities')->nullOnDelete();
            $table->string('contact_phone')->nullable();   // contacts professionnels publics (AGC-01)
            $table->string('contact_email')->nullable();
            $table->string('contact_whatsapp')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('ninea', 40)->nullable();        // PRIVÉS : jamais exposés publiquement
            $table->string('rccm', 60)->nullable();
            $table->string('status', 12)->default('active');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement("ALTER TABLE agencies ADD CONSTRAINT agencies_status_check CHECK (status IN ('active','suspended'))");

        // Zones couvertes par l'agence.
        Schema::create('agency_localities', function (Blueprint $table) {
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('locality_id')->constrained()->cascadeOnDelete();
            $table->primary(['agency_id', 'locality_id']);
        });

        Schema::create('agency_members', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 10);
            $table->boolean('can_see_all')->default(false); // droit étendu : voir toutes les annonces de l'agence
            $table->string('status', 12)->default('active');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'user_id']);
            $table->unique('user_id'); // décision MVP : un compte n'appartient qu'à une seule agence
            $table->index(['agency_id', 'status']);
        });
        DB::statement("ALTER TABLE agency_members ADD CONSTRAINT agency_members_role_check CHECK (role IN ('manager','agent'))");
        DB::statement("ALTER TABLE agency_members ADD CONSTRAINT agency_members_status_check CHECK (status IN ('active','suspended'))");

        Schema::create('agency_invitations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('token_hash', 64)->unique(); // le jeton en clair n'est jamais stocké
            $table->boolean('can_see_all')->default(false);
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'accepted_at', 'revoked_at']);
        });

        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('pending');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status']);
            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE verification_requests ADD CONSTRAINT verification_requests_status_check CHECK (status IN ('pending','approved','rejected'))");
        // Une seule demande en attente par agence.
        DB::statement("CREATE UNIQUE INDEX verification_requests_one_pending ON verification_requests (agency_id) WHERE status = 'pending'");

        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_request_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('path'); // disque PRIVÉ : accessible seulement par URL signée temporaire
            $table->string('mime', 60);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
        DB::statement("ALTER TABLE verification_documents ADD CONSTRAINT verification_documents_kind_check CHECK (kind IN ('ninea','rccm','other'))");

        Schema::table('listings', function (Blueprint $table) {
            $table->foreign('agency_id')->references('id')->on('agencies')->restrictOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index(['agency_id', 'status']);
            $table->index('assigned_to');
        });
        Schema::table('mandates', function (Blueprint $table) {
            $table->foreign('agency_id')->references('id')->on('agencies')->restrictOnDelete();
        });

        // Les contrôles du Passeport peuvent porter sur une agence (professionnel vérifié).
        DB::statement('ALTER TABLE verification_checks DROP CONSTRAINT verification_checks_subject_check');
        DB::statement("ALTER TABLE verification_checks ADD CONSTRAINT verification_checks_subject_check CHECK (subject_type IN ('user','property','mandate','agency'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE verification_checks DROP CONSTRAINT verification_checks_subject_check');
        DB::statement("ALTER TABLE verification_checks ADD CONSTRAINT verification_checks_subject_check CHECK (subject_type IN ('user','property','mandate'))");
        Schema::table('mandates', fn (Blueprint $t) => $t->dropForeign(['agency_id']));
        Schema::table('listings', function (Blueprint $t) {
            $t->dropForeign(['agency_id']);
            $t->dropForeign(['assigned_to']);
            $t->dropIndex(['agency_id', 'status']);
            $t->dropIndex(['assigned_to']);
        });
        Schema::dropIfExists('verification_documents');
        Schema::dropIfExists('verification_requests');
        Schema::dropIfExists('agency_invitations');
        Schema::dropIfExists('agency_members');
        Schema::dropIfExists('agency_localities');
        Schema::dropIfExists('agencies');
    }
};
