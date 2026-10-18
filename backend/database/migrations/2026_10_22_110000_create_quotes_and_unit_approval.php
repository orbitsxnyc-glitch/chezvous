<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une unité n'est publique qu'active ET validée par la modération (contrôles préalables à la publication).
        Schema::table('units', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->index(['status', 'approved_at']);
        });

        // Devis figé : photographie du prix et des règles au moment où le voyageur le demande.
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('guest_user_id')->constrained('users')->restrictOnDelete();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('guests');
            $table->jsonb('line_items'); // [{code, label, amount_xof}]
            $table->unsignedBigInteger('total_xof');
            $table->string('currency', 3)->default('XOF');
            $table->jsonb('rules_snapshot'); // tarif et réglages appliqués
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['guest_user_id', 'created_at']);
            $table->index(['unit_id', 'check_in']);
        });
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT quotes_dates_check CHECK (check_out > check_in)');
        DB::statement("ALTER TABLE quotes ADD CONSTRAINT quotes_currency_check CHECK (currency = 'XOF')");
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
