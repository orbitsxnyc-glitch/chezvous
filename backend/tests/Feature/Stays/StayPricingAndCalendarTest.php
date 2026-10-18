<?php

use App\Exceptions\StayRuleException;
use App\Models\User;
use App\Services\Stays\CalendarService;
use App\Services\Stays\PricingService;
use App\Services\Stays\StayDates;
use App\Services\Stays\UnitService;

it('compte les nuits sur un intervalle demi-ouvert et refuse un départ avant l\'arrivée', function () {
    expect(StayDates::make('2027-03-10', '2027-03-13')->nights())->toBe(3);
    expect(fn () => StayDates::make('2027-03-10', '2027-03-10'))->toThrow(StayRuleException::class);
    expect(fn () => StayDates::make('2027-03-10', '2027-03-09'))->toThrow(StayRuleException::class);
});

it('calcule le prix avec le tarif de base, le ménage et des montants entiers', function () {
    $unit = stayUnit(nightly: 40000);
    $unit->ratePlan->update(['cleaning_fee_xof' => 10000]);

    $price = app(PricingService::class)->price($unit->fresh(), StayDates::make('2027-03-10', '2027-03-13'));

    expect($price['nights'])->toBe(3)
        ->and($price['accommodation_xof'])->toBe(120000)
        ->and($price['cleaning_fee_xof'])->toBe(10000)
        ->and($price['subtotal_xof'])->toBe(130000)
        ->and($price['nightly'])->toHaveCount(3);
});

it('applique l\'exception de prix la plus prioritaire nuit par nuit, la nuit de départ exclue', function () {
    $unit = stayUnit(nightly: 40000);
    $svc = app(UnitService::class);
    $host = $unit->owner_user_id ? User::find($unit->owner_user_id) : null;
    $svc->addOverride($unit, $host, '2027-03-11', '2027-03-13', 60000, priority: 1);
    $svc->addOverride($unit, $host, '2027-03-12', '2027-03-14', 90000, priority: 5);

    $price = app(PricingService::class)->price($unit->fresh(), StayDates::make('2027-03-10', '2027-03-14'));

    // 10: base 40k ; 11: 60k ; 12: 90k (priorité 5) ; 13: 90k ; 14 = départ, non facturée.
    expect(collect($price['nightly'])->pluck('amount_xof')->all())->toBe([40000, 60000, 90000, 90000]);
});

it('refuse un séjour sous la durée minimale ou au-dessus de la durée maximale', function () {
    $unit = stayUnit(nightly: 40000);
    $unit->ratePlan->update(['min_nights' => 2, 'max_nights' => 5]);
    $pricing = app(PricingService::class);

    expect(fn () => $pricing->price($unit->fresh(), StayDates::make('2027-03-10', '2027-03-11')))->toThrow(StayRuleException::class, 'minimale');
    expect(fn () => $pricing->price($unit->fresh(), StayDates::make('2027-03-10', '2027-03-17')))->toThrow(StayRuleException::class, 'maximale');
});

it('la durée maximale vient de la configuration, pas du code', function () {
    config(['chezvous.stays.max_stay_nights' => 3]);
    $unit = stayUnit();

    expect(fn () => app(PricingService::class)->price($unit, StayDates::make('2027-03-10', '2027-03-15')))->toThrow(StayRuleException::class);
});

it('refuse de tarifer une unité sans tarif', function () {
    $unit = stayUnit(nightly: null);

    expect(fn () => app(PricingService::class)->price($unit, StayDates::make('2027-03-10', '2027-03-12')))->toThrow(StayRuleException::class, 'tarif');
});

it('une fermeture bloque les dates qu\'elle couvre mais pas le jour de son départ', function () {
    $unit = stayUnit();
    $host = User::find($unit->owner_user_id);
    $calendar = app(CalendarService::class);
    $calendar->block($unit, $host, '2027-03-10', '2027-03-15');

    expect($calendar->isAvailable($unit, StayDates::make('2027-03-08', '2027-03-11')))->toBeFalse()   // empiète au début
        ->and($calendar->isAvailable($unit, StayDates::make('2027-03-12', '2027-03-13')))->toBeFalse() // à l'intérieur
        ->and($calendar->isAvailable($unit, StayDates::make('2027-03-14', '2027-03-18')))->toBeFalse() // empiète à la fin
        ->and($calendar->isAvailable($unit, StayDates::make('2027-03-15', '2027-03-18')))->toBeTrue()  // arrive le jour du départ
        ->and($calendar->isAvailable($unit, StayDates::make('2027-03-07', '2027-03-10')))->toBeTrue(); // part le jour de l'arrivée
});

it('refuse une fermeture plus longue que la limite configurée', function () {
    config(['chezvous.stays.max_block_days' => 10]);
    $unit = stayUnit();
    $host = User::find($unit->owner_user_id);

    expect(fn () => app(CalendarService::class)->block($unit, $host, '2027-03-01', '2027-04-01'))->toThrow(StayRuleException::class);
});
