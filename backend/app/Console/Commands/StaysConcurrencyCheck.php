<?php

namespace App\Console\Commands;

use App\Exceptions\StayRuleException;
use App\Models\Booking;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\Unit;
use App\Models\User;
use App\Services\Stays\BookingService;
use App\Services\Stays\QuoteService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Contrôle d'acceptation du plan (« 100 requêtes concurrentes ») : N demandes sur les mêmes nuits sont acceptées en parallèle
 * par N processus distincts ; exactement UNE doit réussir, les autres recevoir « unavailable ». Outil de développement :
 * refuse de tourner en production, crée ses données (préfixe [CONC]) et les supprime ensuite.
 */
class StaysConcurrencyCheck extends Command
{
    protected $signature = 'stays:concurrency-check {--n=30 : nombre de demandes concurrentes} {--accept-one= : (interne) accepte une demande}';

    protected $description = 'Vérifie qu\'une seule acceptation concurrente réussit sur des dates identiques (développement uniquement).';

    public function handle(BookingService $bookings, QuoteService $quotes): int
    {
        if (app()->isProduction()) {
            $this->error('Interdit en production.');

            return self::FAILURE;
        }
        if ($id = $this->option('accept-one')) {
            return $this->acceptOne($bookings, (int) $id);
        }

        $n = max(2, (int) $this->option('n'));
        $host = User::factory()->create(['name' => '[CONC] hôte']);
        $property = Property::create(['created_by' => $host->id, 'property_type_id' => PropertyType::firstOrFail()->id, 'locality_id' => Locality::where('level', 'neighborhood')->firstOrFail()->id, 'furnished' => true]);
        PropertyImage::create(['property_id' => $property->id, 'path' => 'conc.webp', 'sizes' => ['thumb' => 'conc.webp'], 'is_cover' => true, 'status' => 'ready']);
        $unit = Unit::create(['property_id' => $property->id, 'owner_user_id' => $host->id, 'name' => '[CONC] unité', 'capacity_guests' => 4]);
        $unit->ratePlan()->create(['base_nightly_xof' => 40000]);
        $unit->forceFill(['status' => 'active', 'approved_at' => now()])->save();

        $guests = [];
        $ids = [];
        try {
            $in = now()->addDays(30)->toDateString();
            $out = now()->addDays(33)->toDateString();
            for ($i = 0; $i < $n; $i++) {
                $guests[] = $g = User::factory()->create(['name' => "[CONC] voyageur {$i}"]);
                $ids[] = $bookings->request($quotes->create($unit, $g, $in, $out, 2), $g, Str::random(16))->id;
            }

            $results = Process::pool(function ($pool) use ($ids) {
                foreach ($ids as $id) {
                    $pool->command([PHP_BINARY, base_path('artisan'), 'stays:concurrency-check', "--accept-one={$id}"]);
                }
            })->start()->wait();

            $accepted = collect($results)->filter(fn ($r) => str_contains($r->output(), 'ACCEPTED'))->count();
            $refused = collect($results)->filter(fn ($r) => str_contains($r->output(), 'UNAVAILABLE'))->count();
            $held = DB::table('unit_occupancies')->where('unit_id', $unit->id)->where('status', 'held')->count();
            $this->info("Demandes : {$n} · acceptées : {$accepted} · refusées (409) : {$refused} · verrous posés : {$held}");

            return $accepted === 1 && $refused === $n - 1 && $held === 1 ? self::SUCCESS : self::FAILURE;
        } finally {
            $bookingIds = Booking::where('unit_id', $unit->id)->pluck('id');
            DB::table('outbox_events')->where('aggregate_type', 'Booking')->whereIn('aggregate_id', $bookingIds)->delete();
            DB::table('user_notifications')->whereIn('user_id', array_merge([$host->id], array_map(fn ($g) => $g->id, $guests)))->delete();
            DB::table('unit_occupancies')->where('unit_id', $unit->id)->delete();
            DB::table('booking_events')->whereIn('booking_id', $bookingIds)->delete();
            // audit_logs est en ajout seul : les traces du contrôle restent (acteurs anonymisés par la suppression des comptes).
            Booking::whereIn('id', $bookingIds)->delete();
            DB::table('quotes')->where('unit_id', $unit->id)->delete();
            $unit->forceDelete();
            $property->images()->delete();
            $property->forceDelete();
            User::whereIn('id', array_merge([$host->id], array_map(fn ($g) => $g->id, $guests)))->forceDelete();
        }
    }

    private function acceptOne(BookingService $bookings, int $id): int
    {
        $booking = Booking::findOrFail($id);
        try {
            $bookings->accept($booking, User::findOrFail($booking->unit->owner_user_id));
            $this->line('ACCEPTED');
        } catch (StayRuleException $e) {
            $this->line($e->errorCode === 'unavailable' ? 'UNAVAILABLE' : 'OTHER:'.$e->errorCode);
        }

        return self::SUCCESS;
    }
}
