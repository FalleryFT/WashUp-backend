<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // ← tambah import ini

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduler ───────────────────────────────────────────
Schedule::command('orders:cleanup')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();