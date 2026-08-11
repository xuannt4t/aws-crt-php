<?php

use App\Models\ApiRefreshSession;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:notify-deadlines')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('tasks:generate-recurring')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::call(fn () => ApiRefreshSession::query()
    ->where('expires_at', '<', now()->subDay())
    ->delete())
    ->name('api:prune-refresh-sessions')
    ->daily();
