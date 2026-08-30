<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// why everyMinute: a post scheduled for 09:05 should appear at 09:05, not at
// the top of the next hour. withoutOverlapping stops a slow run from being
// started again before it finished.
Schedule::command('posts:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// Housekeeping: drop expired API tokens once a day.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
