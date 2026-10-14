<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_reports', function (Blueprint $table) {
            $table->string('action', 12)->nullable(); // hide | warn | suspend | none
            $table->string('handler_note', 500)->nullable();
        });
        DB::statement("ALTER TABLE listing_reports ADD CONSTRAINT listing_reports_action_check CHECK (action IS NULL OR action IN ('hide','warn','suspend','none'))");
        DB::statement("CREATE INDEX listing_reports_open_idx ON listing_reports (created_at) WHERE status = 'open'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS listing_reports_open_idx');
        Schema::table('listing_reports', fn (Blueprint $t) => $t->dropColumn(['action', 'handler_note']));
    }
};
