<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE verification_checks DROP CONSTRAINT verification_checks_type_check');
        DB::statement("ALTER TABLE verification_checks ADD CONSTRAINT verification_checks_type_check CHECK (check_type IN ('phone','identity','professional','location','document','field_visit','tourism_license'))");

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_state_check');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_state_check CHECK (state IN ('requested','accepted','awaiting_payment','confirmed','checked_in','completed','declined','expired','payment_failed','cancelled_by_guest','cancelled_by_host','disputed','refunded_partially','refunded_fully'))");

        // Incident ou litige sur un séjour : ouvert par le voyageur ou l'hôte, tranché par un administrateur.
        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->string('opener_role', 8); // guest | host
            $table->string('severity', 8)->default('normal');
            $table->text('description');
            $table->string('status', 10)->default('open');
            $table->string('resolution', 20)->nullable(); // completed | refund_full | refund_partial
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
        DB::statement("ALTER TABLE support_cases ADD CONSTRAINT support_cases_status_check CHECK (status IN ('open','resolved'))");
        DB::statement("ALTER TABLE support_cases ADD CONSTRAINT support_cases_role_check CHECK (opener_role IN ('guest','host'))");
        // Un seul litige ouvert par séjour.
        DB::statement("CREATE UNIQUE INDEX support_cases_one_open ON support_cases (booking_id) WHERE status = 'open'");

        // Reversement à l'hôte : calculé à la fin du séjour (hébergement + ménage, moins les remboursements), payé hors plateforme.
        Schema::create('host_payouts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('host_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_xof');
            $table->string('currency', 3)->default('XOF');
            $table->string('status', 10)->default('pending');
            $table->string('reference', 120)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
            $table->index(['host_user_id', 'status']);
        });
        DB::statement("ALTER TABLE host_payouts ADD CONSTRAINT host_payouts_status_check CHECK (status IN ('pending','on_hold','paid','cancelled'))");
        DB::statement("ALTER TABLE host_payouts ADD CONSTRAINT host_payouts_currency_check CHECK (currency = 'XOF')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT bookings_state_check');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_state_check CHECK (state IN ('requested','accepted','awaiting_payment','confirmed','checked_in','completed','declined','expired','payment_failed','cancelled_by_guest','cancelled_by_host','disputed'))");
        Schema::dropIfExists('host_payouts');
        Schema::dropIfExists('support_cases');
        DB::statement('ALTER TABLE verification_checks DROP CONSTRAINT verification_checks_type_check');
        DB::statement("ALTER TABLE verification_checks ADD CONSTRAINT verification_checks_type_check CHECK (check_type IN ('phone','identity','professional','location','document','field_visit'))");
    }
};
