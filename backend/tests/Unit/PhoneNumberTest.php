<?php

use App\Support\PhoneNumber;

it('normalise les formats courants au format +221', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('+221771234567');
})->with(['771234567', '77 123 45 67', '+221 77 123 45 67', '00221771234567', '221771234567']);

it('rejette les numéros invalides', function (?string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([null, '', '12345', '+33612345678', '871234567', '77123456']);
