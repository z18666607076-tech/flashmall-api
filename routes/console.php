<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('flash-sales:reconcile')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('demo:reset --force')
    ->dailyAt((string) config('demo.reset_at'))
    ->timezone((string) config('demo.timezone'))
    ->when(fn (): bool => (bool) config('demo.enabled'))
    ->withoutOverlapping();
