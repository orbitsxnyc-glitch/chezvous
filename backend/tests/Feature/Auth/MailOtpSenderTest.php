<?php

use App\Mail\OtpCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('envoie l\'OTP par e-mail quand le canal mail est actif et qu\'un e-mail existe', function () {
    config(['chezvous.otp.driver' => 'mail']);
    Mail::fake();
    $user = User::factory()->unverified()->create(['email' => 'awa@example.com']);

    $this->postJson('/api/v1/auth/otp/send', ['phone' => $user->phone, 'purpose' => 'verify_phone'])->assertStatus(202);

    Mail::assertSent(OtpCodeMail::class, fn ($m) => $m->hasTo('awa@example.com') && strlen($m->code) === 6);
});

it('n\'envoie rien par e-mail si le compte n\'a pas d\'adresse', function () {
    config(['chezvous.otp.driver' => 'mail']);
    Mail::fake();
    $user = User::factory()->unverified()->create(['email' => null]);

    $this->postJson('/api/v1/auth/otp/send', ['phone' => $user->phone, 'purpose' => 'verify_phone'])->assertStatus(202);

    Mail::assertNothingSent();
});
