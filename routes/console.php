<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shop:make-admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("Pengguna {$email} tidak dijumpai.");

        return 1;
    }

    $user->forceFill(['is_admin' => true])->save();
    $this->info("{$email} kini admin.");
})->purpose('Jadikan pengguna sebagai admin kedai');

Schedule::command('subscriptions:renew')->dailyAt('02:00')->withoutOverlapping();
