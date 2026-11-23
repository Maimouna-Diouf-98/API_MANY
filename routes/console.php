<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expire les opérations de production non confirmées après 10 minutes (+ webhook)
Schedule::command('transactions:expire-pending')->everyMinute()->withoutOverlapping();
