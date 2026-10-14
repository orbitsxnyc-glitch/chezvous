<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Réglages modifiables sans redéploiement (coordonnées de règlement, etc.).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->jsonb('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('audience', 12);
            $table->unsignedBigInteger('price_xof')->default(0); // entier, sans décimales
            $table->string('period', 12)->default('monthly');
            $table->jsonb('limits');   // active_listings, agents, photos_per_listing, featured_credits (null = à fixer)
            $table->jsonb('features'); // advanced_stats, badge, enriched_agency_page
            $table->boolean('is_free')->default(false);
            $table->boolean('is_active')->default(false); // une formule non configurée n'est jamais proposée
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_audience_check CHECK (audience IN ('individual','agency','both'))");
        DB::statement("ALTER TABLE plans ADD CONSTRAINT plans_period_check CHECK (period IN ('monthly','quarterly','annual'))");

        Schema::create('premium_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('kind', 10);
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedBigInteger('price_xof')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['kind', 'duration_days']);
        });
        DB::statement("ALTER TABLE premium_products ADD CONSTRAINT premium_products_kind_check CHECK (kind IN ('featured','boost'))");

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('subscriber_type', 10); // user | agency
            $table->unsignedBigInteger('subscriber_id');
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->jsonb('plan_snapshot'); // prix, quotas et fonctionnalités figés : modifier le catalogue ne change pas cette souscription
            $table->string('status', 12)->default('pending');
            $table->string('payment_reference', 30)->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('reminded_7d_at')->nullable();
            $table->timestamp('reminded_1d_at')->nullable();
            $table->timestamp('downgrade_deadline_at')->nullable(); // fin du délai pour choisir les annonces à garder
            $table->string('reason')->nullable(); // motif de suspension ou d'annulation
            $table->foreignId('replaced_by_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestamps();
            $table->index(['subscriber_type', 'subscriber_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_subscriber_check CHECK (subscriber_type IN ('user','agency'))");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('pending','active','suspended','cancelled','expired','replaced'))");
        // Au plus une souscription active, et une seule demande en attente, par souscripteur.
        DB::statement("CREATE UNIQUE INDEX subscriptions_one_active ON subscriptions (subscriber_type, subscriber_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX subscriptions_one_pending ON subscriptions (subscriber_type, subscriber_id) WHERE status = 'pending'");

        Schema::create('premium_listings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('listing_id')->constrained()->restrictOnDelete();
            $table->foreignId('premium_product_id')->constrained('premium_products')->restrictOnDelete();
            $table->jsonb('product_snapshot');
            $table->string('kind', 10);
            $table->string('status', 12)->default('pending');
            $table->string('source', 10)->default('payment'); // payment | credit (crédit inclus dans la formule)
            $table->string('payment_reference', 30)->nullable()->unique();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['listing_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
        DB::statement("ALTER TABLE premium_listings ADD CONSTRAINT premium_listings_status_check CHECK (status IN ('pending','active','suspended','cancelled','expired'))");
        DB::statement("ALTER TABLE premium_listings ADD CONSTRAINT premium_listings_source_check CHECK (source IN ('payment','credit'))");
        DB::statement("ALTER TABLE premium_listings ADD CONSTRAINT premium_listings_kind_check CHECK (kind IN ('featured','boost'))");
        // Une option d'un type donné à la fois (active ou en attente) par annonce.
        DB::statement("CREATE UNIQUE INDEX premium_listings_one_open ON premium_listings (listing_id, kind) WHERE status IN ('pending','active')");

        // Règlements hors ligne : jamais « payé en ligne ». L'annonceur peut DÉCLARER ; seul un administrateur CONFIRME.
        Schema::create('offline_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('payable_type', 20); // subscription | premium_listing
            $table->unsignedBigInteger('payable_id');
            $table->string('method', 14);
            $table->unsignedBigInteger('amount_xof');
            $table->string('external_reference', 80)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('proof_path')->nullable(); // stockage PRIVÉ, accès par URL signée
            $table->string('status', 10)->default('declared');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['payable_type', 'payable_id', 'status']);
        });
        DB::statement("ALTER TABLE offline_payments ADD CONSTRAINT offline_payments_payable_check CHECK (payable_type IN ('subscription','premium_listing'))");
        DB::statement("ALTER TABLE offline_payments ADD CONSTRAINT offline_payments_method_check CHECK (method IN ('wave','orange_money','transfer','cash','other'))");
        DB::statement("ALTER TABLE offline_payments ADD CONSTRAINT offline_payments_status_check CHECK (status IN ('declared','confirmed','rejected'))");

        Schema::table('listings', function (Blueprint $table) {
            // Dénormalisé pour le tri par cursor : vrai tant qu'une « Remontée » est active.
            $table->boolean('is_boosted')->default(false);
        });
        DB::statement("CREATE INDEX listings_public_boost_idx ON listings (is_boosted DESC, freshness_bucket, published_at DESC, id DESC) WHERE status = 'published'");

        DB::statement('CREATE SEQUENCE IF NOT EXISTS payment_reference_seq');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS payment_reference_seq');
        DB::statement('DROP INDEX IF EXISTS listings_public_boost_idx');
        Schema::table('listings', fn (Blueprint $t) => $t->dropColumn('is_boosted'));
        Schema::dropIfExists('offline_payments');
        Schema::dropIfExists('premium_listings');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('premium_products');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('settings');
    }
};
