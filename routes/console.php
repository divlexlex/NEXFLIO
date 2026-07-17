<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily reminders for tomorrow's booked appointments (in-app + email + push).
Schedule::command('appointments:remind')->dailyAt('08:00');
