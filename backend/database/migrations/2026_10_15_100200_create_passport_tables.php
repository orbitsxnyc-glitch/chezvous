<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lien entre un annonceur et un bien : propriétaire, mandataire ou intermédiaire.
        Schema::create('mandates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('advertiser_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->string('role', 16);
            $table->string('document_path')->nullable(); // stockage PRIVÉ, accès par URL signée
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 12)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
        });
        DB::statement("ALTER TABLE mandates ADD CONSTRAINT mandates_role_check CHECK (role IN ('owner','mandatary','intermediary'))");
        DB::statement("ALTER TABLE mandates ADD CONSTRAINT mandates_status_check CHECK (status IN ('pending','verified','rejected','expired'))");

        Schema::table('listings', function (Blueprint $table) {
            $table->foreign('mandate_id')->references('id')->on('mandates')->nullOnDelete();
        });

        // Contrôle explicite, daté, rejouable. Jamais une note globale : un enregistrement par contrôle.
        Schema::create('verification_checks', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20); // user | property | mandate
            $table->unsignedBigInteger('subject_id');
            $table->string('check_type', 20);
            $table->string('status', 12);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamp('valid_until')->nullable();
            $table->string('evidence_path')->nullable(); // stockage PRIVÉ
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'check_type', 'performed_at'], 'verification_checks_subject_idx');
        });
        DB::statement("ALTER TABLE verification_checks ADD CONSTRAINT verification_checks_subject_check CHECK (subject_type IN ('user','property','mandate'))");
        DB::statement("ALTER TABLE verification_checks ADD CONSTRAINT verification_checks_type_check CHECK (check_type IN ('phone','identity','professional','location','document','field_visit'))");
        DB::statement("ALTER TABLE verification_checks ADD CONSTRAINT verification_checks_status_check CHECK (status IN ('passed','failed','pending','expired'))");

        // Fraîcheur de la disponibilité : chaque confirmation est conservée, la dernière fait foi.
        Schema::create('availability_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('response', 12);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 12);
            $table->timestamp('confirmed_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'confirmed_at']);
        });
        DB::statement("ALTER TABLE availability_confirmations ADD CONSTRAINT availability_response_check CHECK (response IN ('available','reserved','rented','withdrawn'))");
        DB::statement("ALTER TABLE availability_confirmations ADD CONSTRAINT availability_channel_check CHECK (channel IN ('app','whatsapp','sms','admin','system'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_confirmations');
        Schema::dropIfExists('verification_checks');
        Schema::table('listings', fn (Blueprint $t) => $t->dropForeign(['mandate_id']));
        Schema::dropIfExists('mandates');
    }
};
