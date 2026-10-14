<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Posée uniquement quand un modérateur approuve : sert à reconnaître un compte de confiance (H7).
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('listings', fn (Blueprint $table) => $table->dropColumn('approved_at'));
    }
};
