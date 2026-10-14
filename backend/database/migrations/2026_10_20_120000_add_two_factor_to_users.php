<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();          // chiffré par le modèle ; jamais renvoyé après l'activation
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->jsonb('two_factor_recovery_codes')->nullable(); // codes de secours HACHÉS, à usage unique
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // dernier pas TOTP accepté : un code ne sert qu'une fois
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'two_factor_last_step']));
    }
};
