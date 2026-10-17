<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Comment le compte a été vérifié : « email » (code reçu par e-mail : le numéro n'est PAS prouvé), « sms »
            // (numéro prouvé), ou null (comptes antérieurs et développement : traités comme numéro prouvé).
            $table->string('verified_via', 10)->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verified_via');
        });
    }
};
