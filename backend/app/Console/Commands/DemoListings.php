<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Services\Passport\FreshnessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Données FICTIVES pour le développement et les mesures de performance. Interdit hors environnement local.
 * Tout ce qui est créé est identifiable : e-mail en @demo.chezvous.invalid et titre préfixé « [DÉMO] ».
 */
class DemoListings extends Command
{
    protected $signature = 'demo:listings {count=1000 : nombre d\'annonces à créer} {--purge : supprime toutes les données de démonstration}';

    protected $description = 'Crée (ou supprime) des annonces fictives, uniquement en environnement local.';

    private const DEMO_DOMAIN = 'demo.chezvous.invalid';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Commande réservée à l\'environnement local.');

            return self::FAILURE;
        }

        return $this->option('purge') ? $this->purge() : $this->create((int) $this->argument('count'));
    }

    private function create(int $count): int
    {
        if ($count < 1 || $count > 200000) {
            $this->error('Nombre invalide (1 à 200000).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($count) {
            $users = $this->demoUsers();
            $typeIds = DB::table('property_types')->pluck('id')->all();

            // Propriétés : quartier, type, surface, position autour du repère du quartier, précision exacte ou approximative.
            DB::statement(<<<'SQL'
                INSERT INTO properties (ulid, created_by, property_type_id, locality_id, address, surface_m2, rooms, bedrooms, bathrooms, furnished, location_precision, location, public_location, reference, created_at, updated_at)
                SELECT substr(md5(random()::text || g::text) || md5(g::text), 1, 26),
                       (?::bigint[])[1 + (g % ?)],
                       (?::bigint[])[1 + (g % ?)],
                       l.id, 'Adresse fictive ' || g,
                       20 + (random() * 280)::int, 1 + (random() * 6)::int, 1 + (random() * 5)::int, 1 + (random() * 3)::int,
                       random() < 0.3,
                       CASE WHEN random() < 0.5 THEN 'exact' ELSE 'approximate' END,
                       ST_SetSRID(ST_MakePoint(ST_X(l.centroid::geometry) + (random() - 0.5) * 0.03, ST_Y(l.centroid::geometry) + (random() - 0.5) * 0.03), 4326)::geography,
                       NULL,
                       'DEMO-' || lpad(g::text, 6, '0') || '-' || substr(md5(random()::text), 1, 4),
                       now(), now()
                FROM generate_series(1, ?) g
                JOIN LATERAL (SELECT id, centroid FROM localities WHERE level = 'neighborhood' AND centroid IS NOT NULL ORDER BY id OFFSET (g % 10) LIMIT 1) l ON true
            SQL, ['{'.implode(',', $users).'}', count($users), '{'.implode(',', $typeIds).'}', count($typeIds), $count]);

            DB::update("UPDATE properties SET public_location = CASE location_precision WHEN 'exact' THEN location ELSE ST_Project(location, 150 + random() * 150, random() * 6.28)::geography END WHERE reference LIKE 'DEMO-%' AND public_location IS NULL");

            // Annonces : 90 % publiées et fraîches, le reste dans d'autres états ; confirmations étalées sur 10 jours.
            DB::statement(<<<'SQL'
                INSERT INTO listings (ulid, reference, property_id, advertiser_user_id, transaction, title, description, price_xof, negotiable, deposit_months, advance_months, status, draft_step, published_at, approved_at, expires_at, last_confirmed_at, availability_response, freshness_bucket, created_at, updated_at)
                SELECT substr(md5(random()::text || p.id::text) || md5(p.id::text), 1, 26),
                       'CV-' || extract(year from now())::int || '-' || lpad(nextval('listing_reference_seq')::text, 6, '0'),
                       p.id, p.created_by,
                       CASE WHEN random() < 0.85 THEN 'rent_long' WHEN random() < 0.5 THEN 'sale' ELSE 'rent_furnished_short' END,
                       '[DÉMO] ' || t.label || ' à ' || loc.name,
                       'Annonce fictive pour tests de performance. Ne pas utiliser.',
                       CASE WHEN random() < 0.85 THEN 100000 + (random() * 900000)::int ELSE 10000000 + (random() * 190000000)::int END,
                       random() < 0.4, 2, 1,
                       CASE WHEN random() < 0.9 THEN 'published' WHEN random() < 0.5 THEN 'paused' ELSE 'draft' END,
                       4,
                       now() - (random() * interval '60 days'), now(), now() + (10 + random() * 50) * interval '1 day',
                       now() - (random() * interval '10 days'), 'available', 0, now(), now()
                FROM properties p JOIN property_types t ON t.id = p.property_type_id JOIN localities loc ON loc.id = p.locality_id
                WHERE p.reference LIKE 'DEMO-%' AND NOT EXISTS (SELECT 1 FROM listings x WHERE x.property_id = p.id)
            SQL);

            DB::statement("INSERT INTO property_images (ulid, property_id, path, sizes, position, is_cover, status, created_at, updated_at) SELECT substr(md5(random()::text || p.id::text) || md5(p.id::text), 1, 26), p.id, 'demo/none-large.webp', '{\"thumb\":\"demo/none-thumb.webp\",\"medium\":\"demo/none-medium.webp\",\"large\":\"demo/none-large.webp\"}'::jsonb, 1, true, 'ready', now(), now() FROM properties p WHERE p.reference LIKE 'DEMO-%' AND NOT EXISTS (SELECT 1 FROM property_images i WHERE i.property_id = p.id)");
        });

        app(FreshnessService::class)->refreshAll();
        DB::statement('ANALYZE listings');
        DB::statement('ANALYZE properties');

        $this->info(DB::table('listings')->where('title', 'like', '[DÉMO]%')->count().' annonces fictives au total.');

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function demoUsers(): array
    {
        $ids = [];
        for ($i = 1; $i <= 20; $i++) {
            $email = "demo{$i}@".self::DEMO_DOMAIN;
            $id = DB::table('users')->where('email', $email)->value('id');
            $ids[] = $id ?? DB::table('users')->insertGetId([
                'ulid' => strtolower((string) Str::ulid()), 'name' => "Démo {$i}", 'phone' => sprintf('+22170000%04d', $i),
                'email' => $email, 'password' => Hash::make(Str::random(40)), 'phone_verified_at' => now(),
                'account_type' => AccountType::Individual->value, 'status' => 'active', 'locale' => 'fr', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function purge(): int
    {
        DB::transaction(function () {
            $props = DB::table('properties')->where('reference', 'like', 'DEMO-%')->pluck('id');
            DB::table('availability_confirmations')->whereIn('property_id', $props)->delete();
            DB::table('property_images')->whereIn('property_id', $props)->delete();
            DB::table('listings')->whereIn('property_id', $props)->delete();
            DB::table('properties')->whereIn('id', $props)->delete();
            DB::table('users')->where('email', 'like', '%@'.self::DEMO_DOMAIN)->delete();
        });
        $this->info('Données de démonstration supprimées.');

        return self::SUCCESS;
    }
}
