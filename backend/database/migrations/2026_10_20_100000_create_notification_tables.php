<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal de chaque envoi (NOT-04) : canal, statut, erreur du fournisseur, tentatives.
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('user_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('channel', 10);
            $table->string('status', 10)->default('pending');
            $table->string('provider', 30)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error', 500)->nullable(); // message du fournisseur : jamais de donnée personnelle
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->index(['status', 'channel', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->unique(['notification_id', 'channel']);
        });
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_channel_check CHECK (channel IN ('email','push','whatsapp','sms'))");
        // « stubbed » : canal sans fournisseur réel, jamais présenté comme « envoyé ».
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_status_check CHECK (status IN ('pending','retrying','sent','stubbed','skipped','failed'))");

        // Modèles de message versionnés (NOT-05) : on ne modifie jamais une version, on en crée une nouvelle.
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60);
            $table->string('channel', 10);
            $table->string('locale', 5)->default('fr');
            $table->unsignedInteger('version')->default(1);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'channel', 'locale', 'version']);
            $table->index(['type', 'channel', 'locale', 'is_active']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 10);
            $table->text('push_token'); // chiffré par le modèle
            $table->string('token_hash', 64)->unique(); // un même jeton n'appartient qu'à un appareil
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });
        DB::statement("ALTER TABLE devices ADD CONSTRAINT devices_platform_check CHECK (platform IN ('android','ios','web'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_deliveries');
    }
};
