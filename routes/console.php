<?php

use App\Support\IntegrityRules;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('integrity:audit', function () {
    $this->info('Running automated database integrity audit...');
    $status = IntegrityRules::status();
    $missing = [];

    foreach (['constraints', 'triggers', 'views'] as $type) {
        foreach ($status[$type] as $name => $present) {
            if (! $present) {
                $missing[] = "{$type}: {$name}";
            }
        }
    }

    if (empty($missing)) {
        $this->info('All database separation-of-duties rules, triggers, and views are verified active.');
        Log::info('Database integrity audit passed.');

        return 0;
    }

    $this->error('Integrity breach detected: Missing database rules: '.implode(', ', $missing));
    Log::critical('Database integrity rules compromised!', ['missing' => $missing]);

    return 1;
})->purpose('Audit and verify database integrity constraints and triggers');

Schedule::command('integrity:audit')->dailyAt('02:00');
